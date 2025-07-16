<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Store Staff Management",
 *     description="API endpoints for managing store staff with role-based access control"
 * )
 */
class StoreStaffController extends Controller
{
    use ApiResponseTrait, AuthorizesRequests;

    /**
     * @OA\Get(
     *     path="/api/stores/{store}/staff",
     *     tags={"Store Staff Management"},
     *     summary="List store staff",
     *     description="Get all staff members for a specific store (requires admin or owner role)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Store ID or slug"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store staff retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store staff retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+241123456789"),
     *                 @OA\Property(property="role", type="string", example="manager"),
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="joined_at", type="string", format="date-time", example="2025-07-15T10:30:00Z")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized to manage store staff",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function index(Store $store): JsonResponse
    {
        $this->authorize('manage-store-users', $store);

        $staff = $store->users()->with('pivot')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'business_name' => $user->business_name,
                'role' => $user->pivot->role,
                'permissions' => $user->pivot->permissions ? json_decode($user->pivot->permissions, true) : [],
                'is_active' => $user->pivot->is_active,
                'joined_at' => $user->pivot->joined_at,
            ];
        });

        return $this->successResponse($staff, 'Store staff retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/stores/{store}/staff",
     *     tags={"Store Staff Management"},
     *     summary="Add staff to store",
     *     description="Add a user to the store with a specific role (requires admin or owner role)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Store ID or slug"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "role"},
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="role", type="string", enum={"admin", "manager", "staff"}, example="manager"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"manage_products", "view_orders"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Staff member added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Staff member added successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="role", type="string", example="manager"),
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="User is already a staff member",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User is already a staff member of this store")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized to manage store staff",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function store(Request $request, Store $store): JsonResponse
    {
        $this->authorize('manage-store-users', $store);

        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => ['required', Rule::in(['admin', 'manager', 'staff'])],
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $user = User::findOrFail($validatedData['user_id']);

        // Check if user is already a staff member
        if ($store->hasUser($user)) {
            return $this->errorResponse('User is already a staff member of this store', null, 400);
        }

        // Add user to store
        $store->addUser($user, $validatedData['role'], $validatedData['permissions'] ?? []);

        // Return the added staff member info
        $staffMember = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'business_name' => $user->business_name,
            'role' => $validatedData['role'],
            'permissions' => $validatedData['permissions'] ?? [],
        ];

        return $this->createdResponse($staffMember, 'Staff member added successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/stores/{store}/staff/{user}",
     *     tags={"Store Staff Management"},
     *     summary="Update staff role",
     *     description="Update a staff member's role and permissions (requires admin or owner role)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Store ID or slug"
     *     ),
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="User ID"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="role", type="string", enum={"admin", "manager", "staff"}, example="staff"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"view_orders"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Staff role updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Staff role updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="role", type="string", example="staff"),
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Staff member not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     )
     * )
     */
    public function update(Request $request, Store $store, User $user): JsonResponse
    {
        $this->authorize('manage-store-users', $store);

        // Check if user is a staff member of this store
        if (!$store->hasUser($user)) {
            return $this->errorResponse('User is not a staff member of this store', null, 404);
        }

        $validatedData = $request->validate([
            'role' => ['sometimes', 'required', Rule::in(['admin', 'manager', 'staff'])],
            'permissions' => 'sometimes|nullable|array',
            'permissions.*' => 'string',
        ]);

        // Get current pivot data
        $pivotData = $store->users()->where('user_id', $user->id)->first()->pivot;

        // Update role and permissions
        $store->users()->updateExistingPivot($user->id, [
            'role' => $validatedData['role'] ?? $pivotData->role,
            'permissions' => isset($validatedData['permissions']) 
                ? json_encode($validatedData['permissions']) 
                : $pivotData->permissions,
        ]);

        // Return updated staff member info
        $updatedStaffMember = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'business_name' => $user->business_name,
            'role' => $validatedData['role'] ?? $pivotData->role,
            'permissions' => $validatedData['permissions'] ?? json_decode($pivotData->permissions, true) ?? [],
        ];

        return $this->updatedResponse($updatedStaffMember, 'Staff role updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/stores/{store}/staff/{user}",
     *     tags={"Store Staff Management"},
     *     summary="Remove staff from store",
     *     description="Remove a user from the store staff (requires admin or owner role)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Store ID or slug"
     *     ),
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="User ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Staff member removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Staff member removed successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot remove store owner",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot remove store owner")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Staff member not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     )
     * )
     */
    public function destroy(Store $store, User $user): JsonResponse
    {
        $this->authorize('manage-store-users', $store);

        // Check if user is a staff member of this store
        if (!$store->hasUser($user)) {
            return $this->errorResponse('User is not a staff member of this store', null, 404);
        }

        // Check if trying to remove the store owner
        $userRole = $store->getUserRole($user);
        if ($userRole === 'owner') {
            return $this->errorResponse('Cannot remove store owner', null, 400);
        }

        // Remove user from store
        $store->removeUser($user);

        return $this->deletedResponse('Staff member removed successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/stores/{store}/staff/{user}",
     *     tags={"Store Staff Management"},
     *     summary="Get staff member details",
     *     description="Get detailed information about a specific staff member (requires admin or owner role)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Store ID or slug"
     *     ),
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="User ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Staff member details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Staff member details retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+241123456789"),
     *                 @OA\Property(property="business_name", type="string", example="John's Business"),
     *                 @OA\Property(property="role", type="string", example="manager"),
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="joined_at", type="string", format="date-time", example="2025-07-15T10:30:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Staff member not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     )
     * )
     */
    public function show(Store $store, User $user): JsonResponse
    {
        $this->authorize('manage-store-users', $store);

        // Check if user is a staff member of this store
        if (!$store->hasUser($user)) {
            return $this->errorResponse('User is not a staff member of this store', null, 404);
        }

        $staffMember = $store->users()->where('user_id', $user->id)->with('pivot')->first();

        $staffData = [
            'id' => $staffMember->id,
            'name' => $staffMember->name,
            'email' => $staffMember->email,
            'phone' => $staffMember->phone,
            'business_name' => $staffMember->business_name,
            'city' => $staffMember->city,
            'role' => $staffMember->pivot->role,
            'permissions' => $staffMember->pivot->permissions ? json_decode($staffMember->pivot->permissions, true) : [],
            'is_active' => $staffMember->pivot->is_active,
            'joined_at' => $staffMember->pivot->joined_at,
        ];

        return $this->successResponse($staffData, 'Staff member details retrieved successfully');
    }
}