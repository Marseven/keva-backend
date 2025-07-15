<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Store;
use App\Models\Product;
use App\Models\Order;
use App\Policies\StorePolicy;
use App\Policies\ProductPolicy;
use App\Policies\OrderPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Store::class => StorePolicy::class,
        Product::class => ProductPolicy::class,
        Order::class => OrderPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Additional Gates for specific store permissions
        Gate::define('manage-store-products', function ($user, Store $store) {
            return $user->is_admin || $user->canManageStore($store);
        });

        Gate::define('manage-store-orders', function ($user, Store $store) {
            return $user->is_admin || $user->canManageStore($store);
        });

        Gate::define('manage-store-settings', function ($user, Store $store) {
            return $user->is_admin || 
                   $user->hasRoleInStore($store, 'owner') || 
                   $user->hasRoleInStore($store, 'admin');
        });

        Gate::define('manage-store-users', function ($user, Store $store) {
            return $user->is_admin || 
                   $user->hasRoleInStore($store, 'owner') || 
                   $user->hasRoleInStore($store, 'admin');
        });

        Gate::define('view-store-analytics', function ($user, Store $store) {
            return $user->is_admin || $user->canManageStore($store);
        });

        // Global admin gate
        Gate::define('global-admin', function ($user) {
            return $user->is_admin;
        });
    }
}
