<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Store;

class StorePolicy
{
    /**
     * Determine whether the user can view any stores.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_admin || $user->managedStores()->exists();
    }

    /**
     * Determine whether the user can view the store.
     */
    public function view(User $user, Store $store): bool
    {
        return $user->is_admin || $user->hasStoreAccess($store);
    }

    /**
     * Determine whether the user can create stores.
     */
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can update the store.
     */
    public function update(User $user, Store $store): bool
    {
        return $user->is_admin || $user->hasRoleInStore($store, 'owner') || $user->hasRoleInStore($store, 'admin');
    }

    /**
     * Determine whether the user can delete the store.
     */
    public function delete(User $user, Store $store): bool
    {
        return $user->is_admin || $user->hasRoleInStore($store, 'owner');
    }

    /**
     * Determine whether the user can restore the store.
     */
    public function restore(User $user, Store $store): bool
    {
        return $user->is_admin || $user->hasRoleInStore($store, 'owner');
    }

    /**
     * Determine whether the user can permanently delete the store.
     */
    public function forceDelete(User $user, Store $store): bool
    {
        return $user->is_admin || $user->hasRoleInStore($store, 'owner');
    }

    /**
     * Determine whether the user can manage store settings.
     */
    public function manageSettings(User $user, Store $store): bool
    {
        return $user->is_admin || $user->hasRoleInStore($store, 'owner') || $user->hasRoleInStore($store, 'admin');
    }

    /**
     * Determine whether the user can manage store users.
     */
    public function manageUsers(User $user, Store $store): bool
    {
        return $user->is_admin || $user->hasRoleInStore($store, 'owner') || $user->hasRoleInStore($store, 'admin');
    }

    /**
     * Determine whether the user can add users to the store.
     */
    public function addUser(User $user, Store $store): bool
    {
        return $user->is_admin || $user->hasRoleInStore($store, 'owner') || $user->hasRoleInStore($store, 'admin');
    }

    /**
     * Determine whether the user can remove users from the store.
     */
    public function removeUser(User $user, Store $store): bool
    {
        return $user->is_admin || $user->hasRoleInStore($store, 'owner') || $user->hasRoleInStore($store, 'admin');
    }

    /**
     * Determine whether the user can change user roles in the store.
     */
    public function changeUserRole(User $user, Store $store): bool
    {
        return $user->is_admin || $user->hasRoleInStore($store, 'owner') || $user->hasRoleInStore($store, 'admin');
    }

    /**
     * Determine whether the user can access store analytics.
     */
    public function viewAnalytics(User $user, Store $store): bool
    {
        return $user->is_admin || $user->canManageStore($store);
    }

    /**
     * Determine whether the user can activate/deactivate the store.
     */
    public function toggleStatus(User $user, Store $store): bool
    {
        return $user->is_admin || $user->hasRoleInStore($store, 'owner');
    }
}
