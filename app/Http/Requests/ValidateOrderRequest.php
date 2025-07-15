<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\Product;

class ValidateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_address' => 'required|array',
            'shipping_address.name' => 'required|string|max:255',
            'shipping_address.address' => 'required|string|max:500',
            'shipping_address.city' => 'required|string|max:100',
            'shipping_address.phone' => 'required|string|max:20',
            'billing_address' => 'nullable|array',
            'billing_address.name' => 'required_with:billing_address|string|max:255',
            'billing_address.address' => 'required_with:billing_address|string|max:500',
            'billing_address.city' => 'required_with:billing_address|string|max:100',
            'billing_address.phone' => 'required_with:billing_address|string|max:20',
            'notes' => 'nullable|string|max:1000',
            'shipping_method' => 'nullable|string|max:100',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $this->validateSameStore($validator);
            $this->validateProductAvailability($validator);
        });
    }

    /**
     * Validate that all products belong to the same store.
     */
    protected function validateSameStore(Validator $validator): void
    {
        $items = $this->input('items', []);
        $productIds = collect($items)->pluck('product_id')->toArray();

        if (empty($productIds)) {
            return;
        }

        $stores = Product::whereIn('id', $productIds)
            ->whereNotNull('store_id')
            ->distinct()
            ->pluck('store_id')
            ->toArray();

        if (count($stores) > 1) {
            $validator->errors()->add('items', 'Tous les produits d\'une commande doivent appartenir au même magasin.');
        }
    }

    /**
     * Validate that products are available and have sufficient stock.
     */
    protected function validateProductAvailability(Validator $validator): void
    {
        $items = $this->input('items', []);
        
        foreach ($items as $index => $item) {
            $product = Product::find($item['product_id']);
            
            if (!$product) {
                continue;
            }

            // Check if product is active
            if ($product->status !== 'active') {
                $validator->errors()->add("items.{$index}.product_id", "Le produit '{$product->name}' n'est pas disponible.");
                continue;
            }

            // Check stock availability
            if ($product->track_inventory && !$product->allow_backorder) {
                if ($product->stock_quantity < $item['quantity']) {
                    $validator->errors()->add("items.{$index}.quantity", "Stock insuffisant pour le produit '{$product->name}'. Stock disponible: {$product->stock_quantity}");
                }
            }
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Au moins un produit doit être ajouté à la commande.',
            'items.*.product_id.required' => 'L\'ID du produit est requis.',
            'items.*.product_id.exists' => 'Le produit sélectionné n\'existe pas.',
            'items.*.quantity.required' => 'La quantité est requise.',
            'items.*.quantity.integer' => 'La quantité doit être un nombre entier.',
            'items.*.quantity.min' => 'La quantité doit être au moins 1.',
            'shipping_address.required' => 'L\'adresse de livraison est requise.',
            'shipping_address.name.required' => 'Le nom du destinataire est requis.',
            'shipping_address.address.required' => 'L\'adresse de livraison est requise.',
            'shipping_address.city.required' => 'La ville de livraison est requise.',
            'shipping_address.phone.required' => 'Le numéro de téléphone est requis.',
        ];
    }
}
