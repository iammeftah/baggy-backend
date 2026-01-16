<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse email est requise',
            'email.email' => 'Veuillez fournir une adresse email valide',
            'email.exists' => 'Aucun compte n\'existe avec cette adresse email',
            'otp.required' => 'Le code OTP est requis',
            'otp.size' => 'Le code OTP doit contenir 6 caractères',
        ];
    }
}
