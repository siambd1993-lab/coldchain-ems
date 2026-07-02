<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes immutable audit records for security- and money-relevant actions.
 *
 * Actor and tenant are pulled from the {@see TenantContext} by default so call
 * sites stay terse: `$audit->log('invoice.voided', $invoice, ['old' => …])`.
 */
final class AuditLogger
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    /**
     * @param  array{
     *     old?: array<string,mixed>|null,
     *     new?: array<string,mixed>|null,
     *     description?: string|null,
     *     actor_type?: string,
     *     actor_id?: int|null,
     *     actor_label?: string|null,
     *     branch_id?: int|null,
     *     tenant_id?: int|null,
     * } $attributes
     */
    public function log(string $action, ?Model $auditable = null, array $attributes = []): AuditLog
    {
        $request = request();
        $user = $this->context->user();

        return AuditLog::create([
            'tenant_id' => $attributes['tenant_id']
                ?? $this->context->tenantId()
                ?? $this->attr($auditable, 'tenant_id'),
            'branch_id' => $attributes['branch_id']
                ?? $this->context->activeBranchId()
                ?? $this->attr($auditable, 'branch_id'),
            'actor_type' => $attributes['actor_type'] ?? 'user',
            'actor_id' => $attributes['actor_id'] ?? $this->context->userId(),
            'actor_label' => $attributes['actor_label'] ?? $user?->getAttribute('email'),
            'action' => $action,
            'auditable_type' => $auditable !== null ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'description' => $attributes['description'] ?? null,
            'old_values' => $attributes['old'] ?? null,
            'new_values' => $attributes['new'] ?? null,
            'ip' => $request?->ip(),
            'user_agent' => $request !== null ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'request_id' => $request?->attributes->get('request_id'),
        ]);
    }

    /** Safely read a possibly-absent attribute from a model. */
    private function attr(?Model $model, string $key): mixed
    {
        return $model?->getAttribute($key);
    }
}
