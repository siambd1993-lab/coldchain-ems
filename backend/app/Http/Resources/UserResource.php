<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated principal, as returned by the auth endpoints and embedded in
 * login/refresh responses.
 *
 * Role/permission/branch data is only emitted when the relevant relations are
 * loaded — `preventLazyLoading` is enabled outside production, so touching an
 * unloaded relation would throw. Callers that need these fields must
 * `loadMissing(['roles', 'branches'])` first (the auth controller does).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rolesLoaded    = $this->relationLoaded('roles');
        $branchesLoaded = $this->relationLoaded('branches');

        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'status'            => $this->status,
            'tenant_id'         => $this->tenant_id,
            'is_platform_admin' => (bool) $this->is_platform_admin,

            'home_branch_id'    => $this->branch_id,
            'roles'             => $rolesLoaded ? $this->roleSlugs() : [],
            'permissions'       => $rolesLoaded ? $this->effectivePermissionSlugs() : [],
            'branch_ids'        => $branchesLoaded ? $this->accessibleBranchIds() : [],

            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'locale'             => $this->locale,
            'timezone'           => $this->timezone,

            'email_verified_at'  => $this->email_verified_at?->toIso8601String(),
            'last_login_at'      => $this->last_login_at?->toIso8601String(),
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
