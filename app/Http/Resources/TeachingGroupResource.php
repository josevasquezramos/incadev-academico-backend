<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeachingGroupResource extends JsonResource
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
            'name' => $this->name,
            'course_name' => $this->courseVersion->course->name,
            'course_version' => $this->courseVersion->version,
            'course_version_name' => $this->courseVersion->name,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'status' => $this->status->value,
            'students_count' => $this->enrollments->count(),
            'teachers' => TeacherResource::collection($this->whenLoaded('teachers')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}