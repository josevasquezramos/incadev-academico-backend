<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $classSession = $this->resource['class_session'];
        $students = $this->resource['students'];
        
        return [
            'id' => $classSession->id,
            'title' => $classSession->title,
            'start_time' => $classSession->start_time->toISOString(),
            'end_time' => $classSession->end_time->toISOString(),
            'meet_url' => $classSession->meet_url,
            'module' => [
                'id' => $classSession->module->id,
                'title' => $classSession->module->title,
            ],
            'group' => [
                'id' => $classSession->group->id,
                'name' => $classSession->group->name,
            ],
            'students' => $students,
            'created_at' => $classSession->created_at->toISOString(),
            'updated_at' => $classSession->updated_at->toISOString(),
        ];
    }
}