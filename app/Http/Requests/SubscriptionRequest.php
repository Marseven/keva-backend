<?php
// app/Http/Requests/SubscriptionRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_slug' => 'required|string|exists:plans,slug',
            'payment_method' => 'required|in:airtel_money,moov_money,visa_mastercard',
            'payer_name' => 'required|string|max:255',
            'payer_email' => 'nullable|email|max:255',
            'payer_phone' => 'required|string|max:20',
            'auto_renew' => 'boolean',
            'trial_days' => 'nullable|integer|min:0|max:30',
        ];
    }

    public function messages(): array
    {
        return [
            'plan_slug.required' => 'Le plan est obligatoire.',
            'plan_slug.exists' => 'Le plan sélectionné n\'existe pas.',
            'payment_method.required' => 'La méthode de paiement est obligatoire.',
            'payment_method.in' => 'La méthode de paiement n\'est pas supportée.',
            'payer_name.required' => 'Le nom du payeur est obligatoire.',
            'payer_phone.required' => 'Le numéro de téléphone est obligatoire.',
            'payer_email.email' => 'L\'email doit être valide.',
            'trial_days.max' => 'La période d\'essai ne peut pas dépasser 30 jours.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validation personnalisée du numéro selon la méthode de paiement
            $paymentMethod = $this->input('payment_method');
            $phone = $this->input('payer_phone');

            if ($paymentMethod && $phone) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

                if ($paymentMethod === 'airtel_money' && !preg_match('/^07\d{7}$/', $cleanPhone)) {
                    $validator->errors()->add('payer_phone', 'Numéro Airtel Money invalide (doit commencer par 07).');
                }

                if ($paymentMethod === 'moov_money' && !preg_match('/^06\d{7}$/', $cleanPhone)) {
                    $validator->errors()->add('payer_phone', 'Numéro Moov Money invalide (doit commencer par 06).');
                }
            }
        });
    }
}
