<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Please select an image to upload',
            'image.image' => 'The file must be an image',
            'image.mimes' => 'Only JPEG, JPG, PNG, GIF, and WebP images are allowed',
            'image.max' => 'Image size must not exceed 5MB',
        ];
    }
}
