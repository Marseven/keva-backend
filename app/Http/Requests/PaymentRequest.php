<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|in:airtel_money,moov_money,visa_mastercard',
            'payer_phone' => 'required_if:payment_method,airtel_money,moov_money|string|max:20',
            'payer_name' => 'required|string|max:255',
            'payer_email' => 'nullable|email|max:255',
            'redirect_url' => 'nullable|url',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'L\'ID de la commande est obligatoire.',
            'order_id.exists' => 'La commande spécifiée n\'existe pas.',
            'payment_method.required' => 'La méthode de paiement est obligatoire.',
            'payment_method.in' => 'La méthode de paiement n\'est pas supportée.',
            'payer_phone.required_if' => 'Le numéro de téléphone est obligatoire pour les paiements Mobile Money.',
            'payer_name.required' => 'Le nom du payeur est obligatoire.',
            'payer_email.email' => 'L\'email du payeur doit être valide.',
            'redirect_url.url' => 'L\'URL de redirection doit être valide.',
        ];
    }
}
