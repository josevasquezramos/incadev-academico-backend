<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeachingClassSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'start_time' => $this->start_time->toISOString(),
            'end_time' => $this->end_time->toISOString(),
            'meet_url' => $this->meet_url,
            'materials' => MaterialResource::collection($this->whenLoaded('materials')),
            'module' => new TeachingModuleResource($this->whenLoaded('module')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}