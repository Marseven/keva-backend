<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Store;

class StoreAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  The required permission (view, manage, admin)
     */
    public function handle(Request $request, Closure $next, string $permission = 'view'): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'data' => null
            ], 401);
        }

        // Get store from route parameter or request
        $store = $this->getStoreFromRequest($request);
        
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found',
                'data' => null
            ], 404);
        }

        // Check if user has required permission
        if (!$this->hasPermission($user, $store, $permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions for this store',
                'data' => null
            ], 403);
        }

        // Add store to request for use in controllers
        $request->merge(['resolved_store' => $store]);

        return $next($request);
    }

    /**
     * Get store from request parameters.
     */
    private function getStoreFromRequest(Request $request): ?Store
    {
        // Try to get store from route parameter
        if ($request->route('store')) {
            return $request->route('store');
        }

        // Try to get store from route parameter by ID
        if ($request->route('store_id')) {
            return Store::find($request->route('store_id'));
        }

        // Try to get store from request body
        if ($request->has('store_id')) {
            return Store::find($request->input('store_id'));
        }

        // Try to get store from slug
        if ($request->route('store_slug')) {
            return Store::where('slug', $request->route('store_slug'))->first();
        }

        return null;
    }

    /**
     * Check if user has required permission for the store.
     */
    private function hasPermission($user, Store $store, string $permission): bool
    {
        // Global admin always has access
        if ($user->is_admin) {
            return true;
        }

        // Check user has access to the store
        if (!$user->hasStoreAccess($store)) {
            return false;
        }

        // Get user's role in the store
        $userRole = $user->getRoleInStore($store);

        switch ($permission) {
            case 'view':
                // Any role can view
                return in_array($userRole, ['owner', 'admin', 'manager', 'staff']);

            case 'manage':
                // Only owner, admin, and manager can manage
                return in_array($userRole, ['owner', 'admin', 'manager']);

            case 'admin':
                // Only owner and admin have admin permissions
                return in_array($userRole, ['owner', 'admin']);

            case 'owner':
                // Only owner has owner permissions
                return $userRole === 'owner';

            default:
                return false;
        }
    }
}
