<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
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
            'status' => $this->status->value,
            'enrollment' => [
                'id' => $this->enrollment->id,
                'user_id' => $this->enrollment->user->id,
                'user_name' => $this->enrollment->user->name,
                'user_email' => $this->enrollment->user->email,
            ],
            'class_session' => [
                'id' => $this->classSession->id,
                'title' => $this->classSession->title,
                'start_time' => $this->classSession->start_time->toISOString(),
            ],
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}