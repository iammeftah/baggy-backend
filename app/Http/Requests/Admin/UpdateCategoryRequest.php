<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->ignore($this->category),
            ],
            'description' => 'nullable|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required',
            'name.max' => 'Category name is too long',
            'name.unique' => 'A category with this name already exists',
            'description.max' => 'Description is too long',
            'image.image' => 'The file must be an image',
            'image.mimes' => 'Only JPEG, JPG, PNG, GIF, and WebP images are allowed',
            'image.max' => 'Image size must not exceed 5MB',
        ];
    }
}
