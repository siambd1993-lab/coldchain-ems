<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\InteractsWithApiRequest;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only compliance trail (permission: audit.view). AuditLog is not
 * tenant-global-scoped (system events carry a null tenant), so the tenant
 * filter is applied explicitly here.
 */
final class AuditLogController extends Controller
{
    use InteractsWithApiRequest;

    public function __construct(private readonly TenantContext $context)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->where('tenant_id', $this->context->tenantId())
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->string('action').'%'))
            ->when($request->filled('actor'), function ($q) use ($request): void {
                $term = '%'.$request->string('actor').'%';
                $q->where('actor_label', 'like', $term);
            })
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->string('from').' 00:00:00'))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->string('to').' 23:59:59'))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('description', 'like', $term)
                    ->orWhere('action', 'like', $term)
                    ->orWhere('actor_label', 'like', $term));
            })
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        $rows = $logs->getCollection()->map(static fn (AuditLog $log): array => [
            'id'          => $log->id,
            'action'      => $log->action,
            'description' => $log->description,
            'actor_type'  => $log->actor_type,
            'actor_label' => $log->actor_label,
            'subject'     => $log->auditable_type !== null
                ? class_basename($log->auditable_type).'#'.$log->auditable_id
                : null,
            'old'         => $log->old_values,
            'new'         => $log->new_values,
            'ip'          => $log->ip,
            'created_at'  => $log->created_at?->toIso8601String(),
        ]);

        // Standard {data, meta, links} envelope — a raw paginator puts `total`
        // at the top level and every client here expects meta.total.
        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
            'links' => [
                'next' => $logs->nextPageUrl(),
                'prev' => $logs->previousPageUrl(),
            ],
        ]);
    }
}
