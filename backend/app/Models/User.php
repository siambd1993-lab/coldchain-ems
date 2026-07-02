<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * A staff member of a tenant (or a platform administrator when tenant_id is
 * null and is_platform_admin is true).
 *
 * Authorization data lives in three places, resolved here for the tenancy layer:
 *   - roles()   → permission bundles the user holds
 *   - branches()→ the branch set the user may act in (empty = all)
 *   - the JWT   → a short-lived cache of the above (see getJWTCustomClaims)
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $branch_id
 * @property string $email
 * @property bool $is_platform_admin
 */
class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use BelongsToTenant;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'settings' => 'array',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function homeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /** Branches this user is explicitly granted; empty set means "all". */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user')->withTimestamps();
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    // ── RBAC resolution (consumed by ResolveTenant / TenantContext) ──────

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    /** @return list<string> */
    public function roleSlugs(): array
    {
        return $this->roles->pluck('slug')->all();
    }

    /**
     * Branch ids the user may access. An empty array is meaningful: it denotes
     * an unrestricted principal (owner/admin) who can act in every branch of
     * the tenant.
     *
     * @return list<int>
     */
    public function accessibleBranchIds(): array
    {
        return $this->branches->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    /**
     * Per-user direct permission grants beyond roles. None in v1 — reserved for
     * a future user_permissions table.
     *
     * @return list<string>
     */
    public function directPermissionSlugs(): array
    {
        return [];
    }

    /**
     * The union of every permission granted by the user's roles (read from the
     * authoritative `roles.permissions` column, so custom roles work) plus any
     * direct grants.
     *
     * @return list<string>
     */
    public function effectivePermissionSlugs(): array
    {
        $fromRoles = $this->roles
            ->flatMap(static fn ($role): array => $role->permissions ?? [])
            ->all();

        return array_values(array_unique(array_merge($fromRoles, $this->directPermissionSlugs())));
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // ── JWTSubject ───────────────────────────────────────────────────────

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Persistent claims embedded in the access token. Treated as a cache: the
     * database remains authoritative (see ResolveTenant), so these accelerate
     * the common path without becoming a stale-permission risk.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'roles' => $this->roleSlugs(),
            'branch_ids' => $this->accessibleBranchIds(),
        ];
    }
}
