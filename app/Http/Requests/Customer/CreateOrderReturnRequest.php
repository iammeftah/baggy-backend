<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_number' => 'required|string|exists:orders,order_number',
            'reason' => 'required|in:defective,wrong_item,not_as_described,changed_mind,quality_issues,other',
            'description' => 'required|string|min:10|max:1000',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.condition' => 'nullable|string|max:500',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'order_number.required' => 'Order number is required',
            'order_number.exists' => 'Invalid order number',
            'reason.required' => 'Return reason is required',
            'reason.in' => 'Invalid return reason',
            'description.required' => 'Please provide a description',
            'description.min' => 'Description must be at least 10 characters',
            'items.required' => 'At least one item must be selected',
            'items.*.order_item_id.required' => 'Item ID is required',
            'items.*.quantity.required' => 'Quantity is required',
            'items.*.quantity.min' => 'Quantity must be at least 1',
            'images.*.image' => 'File must be an image',
            'images.*.max' => 'Image size must not exceed 2MB',
        ];
    }
}
