<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable audit record. Written via {@see \App\Services\AuditLogger}; never
 * updated (only created_at is managed). Not tenant-global-scoped because
 * platform staff must be able to read across tenants for investigations —
 * scope explicitly when exposing to tenant users.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $action
 * @property string $actor_type
 */
class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
