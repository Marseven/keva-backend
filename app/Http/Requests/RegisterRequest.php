<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'whatsapp_number' => 'nullable|string|max:20',
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string',
            'selected_plan' => 'required|string|exists:plans,slug',
            'agree_to_terms' => 'required|boolean|accepted',
            'password' => ['required', 'confirmed', Password::min(6)->letters()->numbers()],
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
            'selected_plan.required' => 'Vous devez sélectionner un plan.',
            'selected_plan.exists' => 'Le plan sélectionné n\'existe pas.',
            'agree_to_terms.required' => 'Vous devez accepter les conditions d\'utilisation.',
            'agree_to_terms.accepted' => 'Vous devez accepter les conditions d\'utilisation.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 6 caractères.',
        ];
    }
}
