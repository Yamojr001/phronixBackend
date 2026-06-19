<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'semester_start_date' => 'required|date',
            'semester_duration_weeks' => 'required|integer|min:1|max:52',
            'daily_study_hours' => 'required|integer|min:1|max:24',
        ];
    }
}
