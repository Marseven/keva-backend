<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;
use App\Models\Store;

class OrderPolicy
{
    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_admin || $user->is_active;
    }

    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // Order owner can view their own order
        if ($order->user_id === $user->id) {
            return true;
        }

        // Store managers can view orders from their store
        if ($order->store_id && $user->hasStoreAccess($order->store)) {
            return $user->canManageStore($order->store);
        }

        return false;
    }

    /**
     * Determine whether the user can create orders.
     */
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // Store managers can update orders from their store
        if ($order->store_id && $user->hasStoreAccess($order->store)) {
            return $user->canManageStore($order->store);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // Store owners can delete orders from their store
        if ($order->store_id && $user->hasStoreAccess($order->store)) {
            return $user->hasRoleInStore($order->store, 'owner');
        }

        return false;
    }

    /**
     * Determine whether the user can restore the order.
     */
    public function restore(User $user, Order $order): bool
    {
        return $this->delete($user, $order);
    }

    /**
     * Determine whether the user can permanently delete the order.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can cancel the order.
     */
    public function cancel(User $user, Order $order): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // Order owner can cancel their own order if it's cancellable
        if ($order->user_id === $user->id && $order->can_be_cancelled) {
            return true;
        }

        // Store managers can cancel orders from their store
        if ($order->store_id && $user->hasStoreAccess($order->store)) {
            return $user->canManageStore($order->store);
        }

        return false;
    }

    /**
     * Determine whether the user can update order status.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // Store managers can update order status
        if ($order->store_id && $user->hasStoreAccess($order->store)) {
            return $user->canManageStore($order->store);
        }

        return false;
    }

    /**
     * Determine whether the user can ship the order.
     */
    public function ship(User $user, Order $order): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // Store managers can ship orders from their store
        if ($order->store_id && $user->hasStoreAccess($order->store)) {
            return $user->canManageStore($order->store);
        }

        return false;
    }

    /**
     * Determine whether the user can deliver the order.
     */
    public function deliver(User $user, Order $order): bool
    {
        return $this->ship($user, $order);
    }

    /**
     * Determine whether the user can add notes to the order.
     */
    public function addNotes(User $user, Order $order): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // Order owner can add notes to their own order
        if ($order->user_id === $user->id) {
            return true;
        }

        // Store staff can add notes to orders from their store
        if ($order->store_id && $user->hasStoreAccess($order->store)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view order analytics.
     */
    public function viewAnalytics(User $user, ?Store $store = null): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if ($store) {
            return $user->canManageStore($store);
        }

        return false;
    }

    /**
     * Determine whether the user can refund the order.
     */
    public function refund(User $user, Order $order): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // Store owners and admins can refund orders from their store
        if ($order->store_id && $user->hasStoreAccess($order->store)) {
            return $user->hasRoleInStore($order->store, 'owner') || 
                   $user->hasRoleInStore($order->store, 'admin');
        }

        return false;
    }
}
