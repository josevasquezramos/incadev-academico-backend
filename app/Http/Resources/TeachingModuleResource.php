<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeachingModuleResource extends JsonResource
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
            'description' => $this->description,
            'sort' => $this->sort,
            'classes' => TeachingClassSessionResource::collection($this->whenLoaded('classSessions')),
            'exams' => TeachingExamResource::collection($this->whenLoaded('exams')),
        ];
    }
}