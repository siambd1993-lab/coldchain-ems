<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DeviceChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeviceChannel
 */
class DeviceChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'channel_key'   => $this->channel_key,
            'metric'        => $this->metric,
            'unit'          => $this->unit,
            'label'         => $this->label,
            'min_threshold' => $this->min_threshold !== null ? (float) $this->min_threshold : null,
            'max_threshold' => $this->max_threshold !== null ? (float) $this->max_threshold : null,
            'last_value'    => $this->last_value !== null ? (float) $this->last_value : null,
            'last_value_at' => $this->last_value_at?->toIso8601String(),
            'is_active'     => (bool) $this->is_active,
        ];
    }
}
