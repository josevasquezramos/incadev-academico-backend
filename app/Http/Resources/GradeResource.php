<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
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
            'grade' => (float) $this->grade,
            'feedback' => $this->feedback,
            'enrollment' => [
                'id' => $this->enrollment->id,
                'user_id' => $this->enrollment->user->id,
                'user_name' => $this->enrollment->user->name,
                'user_email' => $this->enrollment->user->email,
            ],
            'exam' => [
                'id' => $this->exam->id,
                'title' => $this->exam->title,
            ],
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}