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
            'buying_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0|gte:buying_price',
            'category_id' => 'required|exists:categories,id',
            'stock_quantity' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required',
            'description.required' => 'Description is required',
            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a number',
            'buying_price.required' => 'Buying price is required',
            'buying_price.numeric' => 'Buying price must be a number',
            'selling_price.required' => 'Selling price is required',
            'selling_price.numeric' => 'Selling price must be a number',
            'selling_price.gte' => 'Selling price must be greater than or equal to buying price',
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Selected category does not exist',
            'stock_quantity.required' => 'Stock quantity is required',
            'stock_quantity.integer' => 'Stock quantity must be a number',
            'images.*.image' => 'Each file must be an image',
            'images.*.max' => 'Each image must not exceed 5MB',
        ];
    }
}
