<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompletedGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasCertificate = $this->certificates->isNotEmpty();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'course_name' => $this->courseVersion->course->name,
            'course_version' => $this->courseVersion->version,
            'course_version_name' => $this->courseVersion->name,
            'course_description' => $this->courseVersion->course->description,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'status' => $this->status->value,
            'teachers' => $this->teachers->map(function($teacher) {
                return [
                    'name' => $teacher->name,
                    'fullname' => $teacher->fullname,
                    'email' => $teacher->email,
                ];
            }),
            'has_certificate' => $hasCertificate,
            'certificate_download_url' => $hasCertificate ? 
                url("/api/student/certificates/{$this->certificates->first()->uuid}/download") : null,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}