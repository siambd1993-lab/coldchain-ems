<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named bundle of permission slugs. System roles (is_system) are seeded from
 * the {@see \App\Enums\Role} enum per tenant; custom roles are tenant-authored.
 *
 * Deliberately NOT tenant-global-scoped: role resolution happens during auth,
 * before the tenant context is booted, so a global scope here could silently
 * filter a user's own roles. Tenant filtering is applied explicitly via
 * {@see scopeForTenant()} in the role-management endpoints.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $slug
 * @property array<int,string> $permissions
 * @property bool $is_system
 */
class Role extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    /** Scope to a tenant's roles plus the global/system roles (null tenant). */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where(function (Builder $q) use ($tenantId): void {
            $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
        });
    }
}
