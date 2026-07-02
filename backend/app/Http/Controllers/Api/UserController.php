<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\InteractsWithApiRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\SyncUserRolesRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\TokenIssuer;
use App\Support\ApiError;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff account management. Tenant-scoped via the global TenantScope (platform
 * admins carry a null tenant_id, so they never appear in a tenant's listing).
 * Role assignment is a separate endpoint gated by users.assign_roles.
 */
final class UserController extends Controller
{
    use InteractsWithApiRequest;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TokenIssuer $tokens,
        private readonly TenantContext $context,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->with(['roles', 'branches'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'password'  => $data['password'], // hashed by the model cast
            'status'    => $data['status'] ?? 'active',
            'branch_id' => $data['branch_id'] ?? null,
            // Accounts created by an admin are considered verified.
            'email_verified_at' => now(),
        ]);

        $user->roles()->sync($data['role_ids'] ?? []);
        $user->branches()->sync($data['branch_ids'] ?? []);
        $user->load(['roles', 'branches']);

        $this->audit->log('user.created', $user, [
            'description' => "Staff account {$user->email} created.",
            'new' => $user->only(['name', 'email', 'status']) + ['roles' => $user->roleSlugs()],
        ]);

        return UserResource::make($user)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $user): UserResource
    {
        return UserResource::make($user->load(['roles', 'branches']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data   = $request->validated();
        $before = $user->only(['name', 'email', 'phone', 'status', 'branch_id']);

        $user->fill(collect($data)->except(['branch_ids', 'password'])->all());

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        if (array_key_exists('branch_ids', $data)) {
            $user->branches()->sync($data['branch_ids']);
        }

        // A suspended account must not keep a live session.
        if (($data['status'] ?? null) === 'suspended') {
            $this->tokens->revokeAllForUser($user, 'suspended');
        }

        $user->load(['roles', 'branches']);

        $this->audit->log('user.updated', $user, [
            'description' => "Staff account {$user->email} updated.",
            'old' => $before,
            'new' => $user->only(['name', 'email', 'phone', 'status', 'branch_id']),
        ]);

        return UserResource::make($user);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->getKey() === $this->context->userId()) {
            return ApiError::make(
                'cannot_delete_self',
                'You cannot delete your own account.',
                Response::HTTP_CONFLICT,
            );
        }

        $user->delete();
        $this->tokens->revokeAllForUser($user, 'account_deleted');

        $this->audit->log('user.deleted', $user, [
            'description' => "Staff account {$user->email} deleted.",
        ]);

        return response()->json(['data' => ['message' => 'User deleted.']]);
    }

    /**
     * Replace the user's role set (permission: users.assign_roles).
     */
    public function syncRoles(SyncUserRolesRequest $request, User $user): UserResource
    {
        $before = $user->roleSlugs();

        $user->roles()->sync($request->validated()['role_ids']);
        $user->load(['roles', 'branches']);

        $this->audit->log('user.roles_changed', $user, [
            'description' => "Roles for {$user->email} changed.",
            'old' => ['roles' => $before],
            'new' => ['roles' => $user->roleSlugs()],
        ]);

        return UserResource::make($user);
    }
}
