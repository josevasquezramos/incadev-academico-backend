<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $exam = $this->resource['exam'];
        $students = $this->resource['students'];
        
        return [
            'id' => $exam->id,
            'title' => $exam->title,
            'start_time' => $exam->start_time->toISOString(),
            'end_time' => $exam->end_time->toISOString(),
            'exam_url' => $exam->exam_url,
            'module' => [
                'id' => $exam->module->id,
                'title' => $exam->module->title,
            ],
            'group' => [
                'id' => $exam->group->id,
                'name' => $exam->group->name,
            ],
            'students' => $students,
            'created_at' => $exam->created_at->toISOString(),
            'updated_at' => $exam->updated_at->toISOString(),
        ];
    }
}