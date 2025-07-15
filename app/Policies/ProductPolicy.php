<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Product;
use App\Models\Store;

class ProductPolicy
{
    /**
     * Determine whether the user can view any products.
     */
    public function viewAny(User $user): bool
    {
        return true; // Products are generally viewable by all users
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user, Product $product): bool
    {
        return true; // Products are generally viewable by all users
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user, ?Store $store = null): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if (!$store) {
            return $user->is_active;
        }

        return $user->hasRoleInStore($store, 'owner') || 
               $user->hasRoleInStore($store, 'admin') || 
               $user->hasRoleInStore($store, 'manager');
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // If product has no store, only the owner can update
        if (!$product->store_id) {
            return $product->user_id === $user->id;
        }

        $store = $product->store;
        return $user->hasRoleInStore($store, 'owner') || 
               $user->hasRoleInStore($store, 'admin') || 
               $user->hasRoleInStore($store, 'manager');
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // If product has no store, only the owner can delete
        if (!$product->store_id) {
            return $product->user_id === $user->id;
        }

        $store = $product->store;
        return $user->hasRoleInStore($store, 'owner') || 
               $user->hasRoleInStore($store, 'admin');
    }

    /**
     * Determine whether the user can restore the product.
     */
    public function restore(User $user, Product $product): bool
    {
        return $this->delete($user, $product);
    }

    /**
     * Determine whether the user can permanently delete the product.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can manage product inventory.
     */
    public function manageInventory(User $user, Product $product): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if (!$product->store_id) {
            return $product->user_id === $user->id;
        }

        $store = $product->store;
        return $user->hasRoleInStore($store, 'owner') || 
               $user->hasRoleInStore($store, 'admin') || 
               $user->hasRoleInStore($store, 'manager');
    }

    /**
     * Determine whether the user can publish/unpublish the product.
     */
    public function togglePublish(User $user, Product $product): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if (!$product->store_id) {
            return $product->user_id === $user->id;
        }

        $store = $product->store;
        return $user->hasRoleInStore($store, 'owner') || 
               $user->hasRoleInStore($store, 'admin');
    }

    /**
     * Determine whether the user can feature/unfeature the product.
     */
    public function toggleFeatured(User $user, Product $product): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if (!$product->store_id) {
            return $product->user_id === $user->id;
        }

        $store = $product->store;
        return $user->hasRoleInStore($store, 'owner') || 
               $user->hasRoleInStore($store, 'admin');
    }

    /**
     * Determine whether the user can duplicate the product.
     */
    public function duplicate(User $user, Product $product): bool
    {
        return $this->create($user, $product->store);
    }
}
