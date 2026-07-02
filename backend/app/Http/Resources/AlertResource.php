<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'alert_type'      => $this->alert_type,
            'severity'        => $this->severity,
            'status'          => $this->status,
            'title'           => $this->title,
            'message'         => $this->message,
            'metric'          => $this->metric,
            'threshold_value' => $this->threshold_value !== null ? (float) $this->threshold_value : null,
            'observed_value'  => $this->observed_value !== null ? (float) $this->observed_value : null,
            'branch_id'       => $this->branch_id,
            'chamber_id'      => $this->chamber_id,
            'chamber'         => $this->whenLoaded('chamber', fn () => [
                'id'   => $this->chamber->id,
                'name' => $this->chamber->name,
            ]),
            'device_id'        => $this->device_id,
            'triggered_at'     => $this->triggered_at?->toIso8601String(),
            'acknowledged_at'  => $this->acknowledged_at?->toIso8601String(),
            'acknowledged_by'  => $this->acknowledged_by,
            'resolved_at'      => $this->resolved_at?->toIso8601String(),
            'resolution_note'  => $this->resolution_note,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
