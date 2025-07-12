<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_address' => 'required|array',
            'shipping_address.name' => 'required|string|max:255',
            'shipping_address.phone' => 'required|string|max:20',
            'shipping_address.address' => 'required|string',
            'shipping_address.city' => 'required|string|max:255',
            'shipping_address.postal_code' => 'nullable|string|max:10',

            'billing_address' => 'nullable|array',
            'billing_address.name' => 'nullable|string|max:255',
            'billing_address.phone' => 'nullable|string|max:20',
            'billing_address.address' => 'nullable|string',
            'billing_address.city' => 'nullable|string|max:255',
            'billing_address.postal_code' => 'nullable|string|max:10',

            'shipping_method' => 'nullable|string|max:255',
            'payment_method' => 'required|in:airtel_money,moov_money,visa_mastercard,bank_transfer,cash',
            'notes' => 'nullable|string|max:1000',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.product_options' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_address.required' => 'L\'adresse de livraison est obligatoire.',
            'shipping_address.name.required' => 'Le nom pour la livraison est obligatoire.',
            'shipping_address.phone.required' => 'Le téléphone pour la livraison est obligatoire.',
            'shipping_address.address.required' => 'L\'adresse de livraison est obligatoire.',
            'shipping_address.city.required' => 'La ville de livraison est obligatoire.',

            'payment_method.required' => 'La méthode de paiement est obligatoire.',
            'payment_method.in' => 'La méthode de paiement sélectionnée n\'est pas valide.',

            'items.required' => 'Vous devez sélectionner au moins un produit.',
            'items.min' => 'Vous devez sélectionner au moins un produit.',
            'items.*.product_id.required' => 'L\'ID du produit est obligatoire.',
            'items.*.product_id.exists' => 'Le produit sélectionné n\'existe pas.',
            'items.*.quantity.required' => 'La quantité est obligatoire.',
            'items.*.quantity.min' => 'La quantité doit être d\'au moins 1.',
        ];
    }
}
