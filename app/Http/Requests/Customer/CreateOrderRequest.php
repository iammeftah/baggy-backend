<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_address' => 'required|string|max:500',
            'shipping_city' => 'required|string|max:100',
            'shipping_phone' => 'required|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_address.required' => 'Shipping address is required',
            'shipping_address.max' => 'Shipping address is too long',
            'shipping_city.required' => 'City is required',
            'shipping_city.max' => 'City name is too long',
            'shipping_phone.required' => 'Phone number is required',
            'shipping_phone.max' => 'Phone number is too long',
            'notes.max' => 'Notes are too long',
        ];
    }
}
