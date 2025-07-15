<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/user/profile",
     *     summary="Get user profile",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     )
     * )
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['activeSubscription', 'products', 'orders']);
        
        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/user/profile",
     *     summary="Update user profile",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", example="Jean"),
     *             @OA\Property(property="last_name", type="string", example="Mabiala"),
     *             @OA\Property(property="phone", type="string", example="+241123456789"),
     *             @OA\Property(property="whatsapp_number", type="string", example="+241123456789"),
     *             @OA\Property(property="business_name", type="string", example="Boutique Mabiala"),
     *             @OA\Property(property="business_type", type="string", example="Alimentation"),
     *             @OA\Property(property="city", type="string", example="Libreville"),
     *             @OA\Property(property="address", type="string", example="123 Rue de la Paix"),
     *             @OA\Property(property="timezone", type="string", example="Africa/Libreville"),
     *             @OA\Property(property="language", type="string", example="fr")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     )
     * )
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'whatsapp_number' => 'sometimes|string|max:20',
            'business_name' => 'sometimes|string|max:255',
            'business_type' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'timezone' => 'sometimes|string|max:50',
            'language' => 'sometimes|string|max:10',
            'preferences' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->only([
            'first_name', 'last_name', 'phone', 'whatsapp_number',
            'business_name', 'business_type', 'city', 'address',
            'timezone', 'language', 'preferences'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user->fresh()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/user/avatar",
     *     summary="Upload user avatar",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="avatar", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Avatar uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Avatar uploaded successfully"),
     *             @OA\Property(property="avatar_url", type="string", example="https://example.com/storage/avatars/user123.jpg")
     *         )
     *     )
     * )
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        
        // Delete old avatar if exists
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $avatarPath = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $avatarPath]);

        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'avatar_url' => $user->avatar_url
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/user/password",
     *     summary="Change user password",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="current_password", type="string", example="currentpass123"),
     *             @OA\Property(property="new_password", type="string", example="newpass123"),
     *             @OA\Property(property="new_password_confirmation", type="string", example="newpass123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password changed successfully")
     *         )
     *     )
     * )
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/user/dashboard",
     *     summary="Get user dashboard data",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_products", type="integer", example=15),
     *                 @OA\Property(property="total_orders", type="integer", example=25),
     *                 @OA\Property(property="total_revenue", type="number", example=15000.50),
     *                 @OA\Property(property="active_subscription", type="object"),
     *                 @OA\Property(property="recent_orders", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="plan_usage", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $totalProducts = $user->products()->count();
        $totalOrders = $user->orders()->count();
        $totalRevenue = $user->orders()->where('status', 'completed')->sum('total_amount');
        $activeSubscription = $user->activeSubscription;
        $recentOrders = $user->orders()->with('items.product')->latest()->limit(5)->get();
        
        $planUsage = null;
        if ($activeSubscription) {
            $plan = $user->getCurrentPlan();
            $planUsage = [
                'products_used' => $totalProducts,
                'products_limit' => $plan->max_products,
                'products_percentage' => $plan->max_products > 0 ? ($totalProducts / $plan->max_products) * 100 : 0
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_products' => $totalProducts,
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'active_subscription' => $activeSubscription,
                'recent_orders' => $recentOrders,
                'plan_usage' => $planUsage
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/user/account",
     *     summary="Delete user account",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="password", type="string", example="userpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Account deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Account deleted successfully")
     *         )
     *     )
     * )
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password is incorrect'
            ], 400);
        }

        // Delete avatar if exists
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete user
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully'
        ]);
    }
}
