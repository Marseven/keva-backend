<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = auth()->id();

        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users')->ignore($userId)],
            'whatsapp_number' => 'nullable|string|max:20',
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string',
            'avatar' => 'nullable|image|max:1024',
            'timezone' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:2',
            'preferences' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Le prénom est obligatoire.',
            'last_name.required' => 'Le nom est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'business_name.required' => 'Le nom de l\'entreprise est obligatoire.',
            'business_type.required' => 'Le type d\'entreprise est obligatoire.',
            'city.required' => 'La ville est obligatoire.',
            'address.required' => 'L\'adresse est obligatoire.',
            'avatar.image' => 'L\'avatar doit être une image.',
            'avatar.max' => 'L\'avatar ne doit pas dépasser 1 MB.',
        ];
    }
}
