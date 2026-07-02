<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Concerns\InteractsWithApiRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Support\ApiError;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role management. The Role model is deliberately not tenant-global-scoped
 * (see the model docblock), so every query and binding here applies the tenant
 * filter explicitly. System roles are readable but immutable.
 */
final class RoleController extends Controller
{
    use InteractsWithApiRequest;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $context,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = Role::query()
            ->where('tenant_id', $this->context->tenantId())
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return RoleResource::collection($roles);
    }

    /**
     * The grouped permission catalog the role editor renders as checkboxes.
     */
    public function permissions(): JsonResponse
    {
        $groups = collect(Permission::cases())
            ->groupBy(fn (Permission $p): string => $p->module())
            ->map(fn ($perms, string $module): array => [
                'module'      => $module,
                'label'       => ucwords(str_replace('_', ' ', $module)),
                'permissions' => $perms->map(fn (Permission $p): array => [
                    'value' => $p->value,
                    'label' => $p->label(),
                ])->values()->all(),
            ])
            ->values();

        return response()->json(['data' => $groups]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'tenant_id'   => $this->context->tenantId(),
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? null,
            'permissions' => array_values(array_unique($data['permissions'])),
            'is_system'   => false,
        ]);

        $this->audit->log('role.created', $role, [
            'description' => "Custom role \"{$role->name}\" created.",
            'new' => $role->only(['name', 'slug', 'permissions']),
        ]);

        return RoleResource::make($role)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource|JsonResponse
    {
        if ($error = $this->guardMutable($role)) {
            return $error;
        }

        $before = $role->only(['name', 'description', 'permissions']);
        $data   = $request->validated();

        if (isset($data['permissions'])) {
            $data['permissions'] = array_values(array_unique($data['permissions']));
        }

        $role->update($data);

        $this->audit->log('role.updated', $role, [
            'description' => "Role \"{$role->name}\" updated.",
            'old' => $before,
            'new' => $role->only(['name', 'description', 'permissions']),
        ]);

        return RoleResource::make($role->loadCount('users'));
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($error = $this->guardMutable($role)) {
            return $error;
        }

        if ($role->users()->exists()) {
            return ApiError::make(
                'role_in_use',
                'This role is still assigned to one or more users. Remove it from them first.',
                Response::HTTP_CONFLICT,
            );
        }

        $role->delete();

        $this->audit->log('role.deleted', $role, [
            'description' => "Role \"{$role->name}\" deleted.",
        ]);

        return response()->json(['data' => ['message' => 'Role deleted.']]);
    }

    /**
     * 404 for other tenants' roles (Role has no global tenant scope, so the
     * route binding alone does not isolate); 409 for immutable system roles.
     */
    private function guardMutable(Role $role): ?JsonResponse
    {
        if ($role->tenant_id !== $this->context->tenantId()) {
            return ApiError::make('NOT_FOUND', 'Resource not found.', Response::HTTP_NOT_FOUND);
        }

        if ($role->is_system) {
            return ApiError::make(
                'system_role_immutable',
                'System roles cannot be modified or deleted.',
                Response::HTTP_CONFLICT,
            );
        }

        return null;
    }
}
