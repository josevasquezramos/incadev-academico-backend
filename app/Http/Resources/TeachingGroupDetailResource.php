<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeachingGroupDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $group = $this->resource['group'];
        $modules = $this->resource['modules'];
        $students = $this->resource['students'];
        
        return [
            'id' => $group->id,
            'name' => $group->name,
            'course_name' => $group->courseVersion->course->name,
            'course_version' => $group->courseVersion->version,
            'course_version_name' => $group->courseVersion->name,
            'course_description' => $group->courseVersion->course->description,
            'course_image' => $group->courseVersion->course->image_path,
            'start_date' => $group->start_date->format('Y-m-d'),
            'end_date' => $group->end_date->format('Y-m-d'),
            'status' => $group->status->value,
            'teachers' => TeacherResource::collection($group->teachers),
            'students' => $students,
            'modules' => TeachingModuleResource::collection($modules),
            'created_at' => $group->created_at->toISOString(),
        ];
    }
}