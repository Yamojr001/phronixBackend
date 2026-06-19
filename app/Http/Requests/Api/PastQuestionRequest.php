<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PastQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_code' => 'required|string|max:50',
            'year' => 'required|integer|min:1900|max:'.date('Y'),
            'institution' => 'nullable|string|max:255',
            'file' => 'required|file|max:20480|mimes:pdf,docx,png,jpg,jpeg',
        ];
    }
}
