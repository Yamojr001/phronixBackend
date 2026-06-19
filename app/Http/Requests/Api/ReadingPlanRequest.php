<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ReadingPlanRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $course = $this->route('course');

        if ($course instanceof \App\Models\Course) {
            $this->merge([
                'course_id' => $course->id,
            ]);

            return;
        }

        if ($course !== null && !$this->filled('course_id')) {
            $this->merge([
                'course_id' => $course,
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => 'required|exists:courses,id',
            'duration_weeks' => 'nullable|integer|min:1|max:12',
        ];
    }
}
