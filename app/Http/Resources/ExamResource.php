<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Obtener la nota del usuario actual para este examen
        $userGrade = null;
        if ($this->relationLoaded('grades')) {
            $userGrade = $this->grades->first(function ($grade) use ($request) {
                return $grade->enrollment->user_id === $request->user()->id;
            });
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'start_time' => $this->start_time->toISOString(),
            'end_time' => $this->end_time->toISOString(),
            'exam_url' => $this->exam_url,
            'my_grade' => $userGrade ? [
                'grade' => $userGrade->grade,
                'feedback' => $userGrade->feedback,
                'recorded_at' => $userGrade->created_at->toISOString(),
            ] : null,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}