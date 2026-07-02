<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\InteractsWithApiRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Alert inbox: list, acknowledge, resolve. Alerts are raised by the telemetry
 * pipeline (or seeded demo data); operators work them off here.
 */
final class AlertController extends Controller
{
    use InteractsWithApiRequest;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $alerts = Alert::query()
            ->with('chamber:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')))
            ->when($request->filled('chamber_id'), fn ($q) => $q->where('chamber_id', $request->integer('chamber_id')))
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'acknowledged' THEN 1 ELSE 2 END")
            ->orderByDesc('triggered_at')
            ->paginate($this->perPage($request));

        return AlertResource::collection($alerts);
    }

    public function acknowledge(Request $request, Alert $alert): AlertResource
    {
        /** @var User $user */
        $user = $request->user();

        $alert->acknowledge($user);

        $this->audit->log('alert.acknowledged', $alert, [
            'description' => "Alert \"{$alert->title}\" acknowledged.",
        ]);

        return AlertResource::make($alert->refresh()->load('chamber:id,name'));
    }

    public function resolve(Request $request, Alert $alert): AlertResource
    {
        $request->validate(['resolution_note' => ['nullable', 'string', 'max:500']]);

        /** @var User $user */
        $user = $request->user();

        $alert->resolve($user, $request->input('resolution_note'));

        $this->audit->log('alert.resolved', $alert, [
            'description' => "Alert \"{$alert->title}\" resolved.",
            'new' => ['resolution_note' => $request->input('resolution_note')],
        ]);

        return AlertResource::make($alert->refresh()->load('chamber:id,name'));
    }
}
