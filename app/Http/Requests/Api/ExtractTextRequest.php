<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ExtractTextRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => 'nullable|file|max:51200|mimes:pdf,png,jpg,jpeg,txt,ppt,pptx,pptm,docx',
            'base64_file' => 'nullable|string',
            'mime_type' => 'nullable|string',
            'file_name' => 'nullable|string',
        ];
    }
}
