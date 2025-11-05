<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Obtener la asistencia del usuario actual para esta clase
        $userAttendance = null;
        if ($this->relationLoaded('attendances')) {
            $userAttendance = $this->attendances->first(function ($attendance) use ($request) {
                return $attendance->enrollment->user_id === $request->user()->id;
            });
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'start_time' => $this->start_time->toISOString(),
            'end_time' => $this->end_time->toISOString(),
            'meet_url' => $this->meet_url,
            'materials' => MaterialResource::collection($this->whenLoaded('materials')),
            'my_attendance' => $userAttendance ? [
                'status' => $userAttendance->status->value,
                'recorded_at' => $userAttendance->created_at->toISOString(),
            ] : null,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}