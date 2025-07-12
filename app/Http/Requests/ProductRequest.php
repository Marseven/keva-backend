<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product') ? $this->route('product')->id : null;

        return [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $productId,
            'track_inventory' => 'boolean',
            'stock_quantity' => 'required_if:track_inventory,true|integer|min:0',
            'min_stock_level' => 'nullable|integer|min:0',
            'allow_backorder' => 'boolean',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|array',
            'dimensions.length' => 'nullable|numeric|min:0',
            'dimensions.width' => 'nullable|numeric|min:0',
            'dimensions.height' => 'nullable|numeric|min:0',
            'condition' => 'required|in:new,used,refurbished',
            'featured_image' => 'nullable|image|max:2048',
            'gallery_images.*' => 'nullable|image|max:2048',
            'video_url' => 'nullable|url',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'attributes' => 'nullable|array',
            'variants' => 'nullable|array',
            'status' => 'required|in:draft,active,inactive,archived',
            'is_featured' => 'boolean',
            'is_digital' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du produit est obligatoire.',
            'category_id.required' => 'La catégorie est obligatoire.',
            'category_id.exists' => 'La catégorie sélectionnée n\'existe pas.',
            'description.required' => 'La description est obligatoire.',
            'price.required' => 'Le prix est obligatoire.',
            'price.numeric' => 'Le prix doit être un nombre.',
            'price.min' => 'Le prix doit être positif.',
            'sku.unique' => 'Ce code produit est déjà utilisé.',
            'stock_quantity.required_if' => 'La quantité en stock est obligatoire si le suivi des stocks est activé.',
            'featured_image.image' => 'L\'image principale doit être un fichier image.',
            'featured_image.max' => 'L\'image principale ne doit pas dépasser 2 MB.',
            'gallery_images.*.image' => 'Toutes les images doivent être des fichiers image.',
            'gallery_images.*.max' => 'Les images ne doivent pas dépasser 2 MB chacune.',
            'video_url.url' => 'L\'URL de la vidéo doit être valide.',
            'condition.in' => 'La condition doit être : neuf, usagé ou reconditionné.',
            'status.in' => 'Le statut doit être : brouillon, actif, inactif ou archivé.',
        ];
    }
}
