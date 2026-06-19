<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => 'required|exists:courses,id',
            'test_type' => 'required|string',
            'score' => 'required|numeric|min:0|max:100',
            'weak_topics' => 'nullable|array',
            'answers' => 'nullable|array',
        ];
    }
}
