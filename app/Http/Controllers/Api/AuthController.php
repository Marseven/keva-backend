<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Models\Plan;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     tags={"Authentification"},
     *     summary="Inscription d'un nouvel utilisateur",
     *     description="Créer un nouveau compte utilisateur avec les informations de l'entreprise",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name","last_name","email","phone","business_name","business_type","city","address","selected_plan","agree_to_terms","password","password_confirmation"},
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="phone", type="string", example="+24177123456"),
     *             @OA\Property(property="whatsapp_number", type="string", example="+24177123456"),
     *             @OA\Property(property="business_name", type="string", example="Mon Entreprise"),
     *             @OA\Property(property="business_type", type="string", example="Vêtements et mode"),
     *             @OA\Property(property="city", type="string", example="Libreville"),
     *             @OA\Property(property="address", type="string", example="123 Rue de la Paix"),
     *             @OA\Property(property="selected_plan", type="string", example="basic"),
     *             @OA\Property(property="agree_to_terms", type="boolean", example=true),
     *             @OA\Property(property="password", type="string", format="password", example="motdepasse123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="motdepasse123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Inscription réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inscription réussie"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User"),
     *                 @OA\Property(property="token", type="string", example="1|abc123..."),
     *                 @OA\Property(property="plan", ref="#/components/schemas/Plan")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            // Vérifier que le plan existe
            $plan = Plan::where('slug', $request->selected_plan)->first();
            if (!$plan) {
                return $this->errorResponse('Plan sélectionné invalide', null, 422);
            }

            // Créer l'utilisateur
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'whatsapp_number' => $request->whatsapp_number ?? $request->phone,
                'business_name' => $request->business_name,
                'business_type' => $request->business_type,
                'city' => $request->city,
                'address' => $request->address,
                'selected_plan' => $request->selected_plan,
                'agree_to_terms' => $request->agree_to_terms,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(), // Auto-vérification pour simplifier
            ]);

            // Créer le token d'authentification
            $token = $user->createToken('auth_token')->plainTextToken;

            // Charger les relations
            $user->load(['activeSubscription.plan']);

            return $this->createdResponse([
                'user' => $user,
                'token' => $token,
                'plan' => $plan,
            ], 'Inscription réussie');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de l\'inscription', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Authentification"},
     *     summary="Connexion utilisateur",
     *     description="Authentifier un utilisateur avec email et mot de passe",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="motdepasse123"),
     *             @OA\Property(property="remember", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Connexion réussie"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User"),
     *                 @OA\Property(property="token", type="string", example="1|abc123..."),
     *                 @OA\Property(property="expires_at", type="string", format="date-time", example="2024-02-15T10:30:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Identifiants incorrects",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Compte désactivé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Votre compte est désactivé"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return $this->unauthorizedResponse('Email ou mot de passe incorrect');
        }

        $user = Auth::user();

        // Vérifier si le compte est actif
        if (!$user->is_active) {
            Auth::logout();
            return $this->forbiddenResponse('Votre compte est désactivé. Contactez le support.');
        }

        // Révoquer les anciens tokens si nécessaire
        if (!$request->remember) {
            $user->tokens()->delete();
        }

        // Créer un nouveau token
        $tokenName = 'auth_token_' . now()->timestamp;
        $token = $user->createToken($tokenName)->plainTextToken;

        // Mettre à jour la dernière connexion
        $user->updateLastLogin();

        // Charger les relations utiles
        $user->load(['activeSubscription.plan']);

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
            'expires_at' => null, // Sanctum ne gère pas l'expiration par défaut
        ], 'Connexion réussie');
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Authentification"},
     *     summary="Déconnexion utilisateur",
     *     description="Déconnecter l'utilisateur et révoquer le token",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Déconnexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Déconnexion réussie"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        // Révoquer le token actuel
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Déconnexion réussie');
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout-all",
     *     tags={"Authentification"},
     *     summary="Déconnexion de tous les appareils",
     *     description="Révoquer tous les tokens de l'utilisateur",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Déconnexion de tous les appareils réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Déconnexion de tous les appareils réussie"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="revoked_tokens", type="integer", example=3)
     *             )
     *         )
     *     )
     * )
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokensCount = $user->tokens()->count();

        // Révoquer tous les tokens
        $user->tokens()->delete();

        return $this->successResponse([
            'revoked_tokens' => $tokensCount,
        ], 'Déconnexion de tous les appareils réussie');
    }

    /**
     * @OA\Get(
     *     path="/api/profile",
     *     tags={"Utilisateurs"},
     *     summary="Obtenir le profil utilisateur",
     *     description="Récupérer les informations du profil de l'utilisateur connecté",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profil utilisateur récupéré",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profil récupéré"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User"),
     *                 @OA\Property(property="subscription", ref="#/components/schemas/Subscription"),
     *                 @OA\Property(property="plan", ref="#/components/schemas/Plan"),
     *                 @OA\Property(property="stats", type="object",
     *                     @OA\Property(property="products_count", type="integer", example=25),
     *                     @OA\Property(property="orders_count", type="integer", example=150),
     *                     @OA\Property(property="total_revenue", type="number", example=750000)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        // Charger les relations
        $user->load([
            'activeSubscription.plan',
            'products' => function ($query) {
                $query->where('status', 'active');
            },
            'orders' => function ($query) {
                $query->where('status', '!=', 'cancelled');
            }
        ]);

        // Calculer les statistiques
        $stats = [
            'products_count' => $user->products->count(),
            'orders_count' => $user->orders->count(),
            'total_revenue' => $user->orders()
                ->where('payment_status', 'paid')
                ->sum('total_amount'),
            'pending_orders' => $user->orders()
                ->where('status', 'pending')
                ->count(),
        ];

        $subscription = $user->activeSubscription;
        $plan = $subscription ? $subscription->plan : $user->getCurrentPlan();

        return $this->successResponse([
            'user' => $user,
            'subscription' => $subscription,
            'plan' => $plan,
            'stats' => $stats,
        ], 'Profil récupéré');
    }

    /**
     * @OA\Put(
     *     path="/api/profile",
     *     tags={"Utilisateurs"},
     *     summary="Mettre à jour le profil",
     *     description="Modifier les informations du profil utilisateur",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+24177123456"),
     *                 @OA\Property(property="whatsapp_number", type="string", example="+24177123456"),
     *                 @OA\Property(property="business_name", type="string", example="Mon Entreprise"),
     *                 @OA\Property(property="business_type", type="string", example="Vêtements et mode"),
     *                 @OA\Property(property="city", type="string", example="Libreville"),
     *                 @OA\Property(property="address", type="string", example="123 Rue de la Paix"),
     *                 @OA\Property(property="avatar", type="string", format="binary", description="Image de profil")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profil mis à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profil mis à jour avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            // Gérer l'upload de l'avatar
            if ($request->hasFile('avatar')) {
                // Supprimer l'ancien avatar
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }

                // Sauvegarder le nouveau
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $data['avatar'] = $avatarPath;
            }

            // Mettre à jour l'utilisateur
            $user->update($data);

            // Recharger les relations
            $user->fresh(['activeSubscription.plan']);

            return $this->updatedResponse($user, 'Profil mis à jour avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour du profil', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/user/tokens",
     *     tags={"Utilisateurs"},
     *     summary="Lister les sessions actives",
     *     description="Obtenir la liste des tokens/sessions actifs de l'utilisateur",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sessions actives récupérées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sessions actives récupérées"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="auth_token_1642234567"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="last_used_at", type="string", format="date-time"),
     *                     @OA\Property(property="is_current", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getUserTokens(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $tokens = $user->tokens()->get()->map(function ($token) use ($currentTokenId) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'created_at' => $token->created_at,
                'last_used_at' => $token->last_used_at,
                'is_current' => $token->id === $currentTokenId,
            ];
        });

        return $this->successResponse($tokens, 'Sessions actives récupérées');
    }

    /**
     * @OA\Delete(
     *     path="/api/user/tokens/{tokenId}",
     *     tags={"Utilisateurs"},
     *     summary="Révoquer une session",
     *     description="Révoquer un token/session spécifique",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tokenId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du token à révoquer"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Session révoquée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Session révoquée avec succès"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Token non trouvé",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     )
     * )
     */
    public function revokeToken(Request $request, int $tokenId): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        if ($tokenId == $currentTokenId) {
            return $this->errorResponse('Vous ne pouvez pas révoquer votre session actuelle', null, 400);
        }

        $token = $user->tokens()->find($tokenId);

        if (!$token) {
            return $this->notFoundResponse('Session non trouvée');
        }

        $token->delete();

        return $this->successResponse(null, 'Session révoquée avec succès');
    }
}
