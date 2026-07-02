<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'device_uid'       => $this->device_uid,
            'name'             => $this->name,
            'device_type'      => $this->device_type,
            'protocol'         => $this->protocol,
            'status'           => $this->status,
            'branch_id'        => $this->branch_id,
            'chamber_id'       => $this->chamber_id,
            'chamber'          => $this->whenLoaded('chamber', fn () => [
                'id'   => $this->chamber->id,
                'name' => $this->chamber->name,
                'code' => $this->chamber->code,
            ]),
            'model'            => $this->model,
            'manufacturer'     => $this->manufacturer,
            'firmware_version' => $this->firmware_version,
            'last_seen_at'     => $this->last_seen_at?->toIso8601String(),
            'channels'         => DeviceChannelResource::collection($this->whenLoaded('channels')),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
