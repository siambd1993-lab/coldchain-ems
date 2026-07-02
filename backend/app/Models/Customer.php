<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * A depositor who rents storage from the tenant. Doubles as a self-service
 * portal principal (separate `customers` auth guard) when a password is set.
 *
 * Monetary fields are poisha (minor units).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property int $balance_poisha
 */
class Customer extends Authenticatable implements JWTSubject
{
    use BelongsToTenant;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'credit_limit_poisha' => 'integer',
            'opening_balance_poisha' => 'integer',
            'balance_poisha' => 'integer',
            'settings' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** True when adding the given charge (poisha) would exceed the credit limit. */
    public function wouldExceedCreditLimit(int $additionalCharge): bool
    {
        if ($this->credit_limit_poisha <= 0) {
            return false; // 0 = no limit configured
        }

        return ($this->balance_poisha + $additionalCharge) > $this->credit_limit_poisha;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /** @return array<string, mixed> */
    public function getJWTCustomClaims(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'principal' => 'customer',
        ];
    }
}
