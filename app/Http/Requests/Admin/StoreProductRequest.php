<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'specifications' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'stock_quantity' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',

            // Image validation - multiple images allowed
            'images' => 'nullable|array|max:5', // Maximum 5 images
            'images.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:10240', // 10MB max per image
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required',
            'name.max' => 'Product name is too long',
            'description.required' => 'Description is required',
            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a number',
            'price.min' => 'Price cannot be negative',
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Selected category does not exist',
            'stock_quantity.required' => 'Stock quantity is required',
            'stock_quantity.integer' => 'Stock quantity must be a number',
            'stock_quantity.min' => 'Stock quantity cannot be negative',

            // Image messages
            'images.array' => 'Images must be sent as an array',
            'images.max' => 'You can upload maximum 5 images',
            'images.*.image' => 'Each file must be an image',
            'images.*.mimes' => 'Images must be JPEG, JPG, PNG, GIF, or WebP format',
            'images.*.max' => 'Each image must not exceed 10MB',
        ];
    }
}
