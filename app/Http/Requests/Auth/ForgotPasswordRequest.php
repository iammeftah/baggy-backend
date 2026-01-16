<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse email est requise',
            'email.email' => 'Veuillez fournir une adresse email valide',
            'email.exists' => 'Aucun compte n\'existe avec cette adresse email',
        ];
    }
}
