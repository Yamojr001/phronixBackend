<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StudyRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => 'required|exists:courses,id',
            'topic' => 'nullable|string|max:255',
            'question' => 'nullable|string|max:2000',
            'task_id' => 'nullable|string',
            'is_completed' => 'nullable|boolean',
        ];
    }
}
