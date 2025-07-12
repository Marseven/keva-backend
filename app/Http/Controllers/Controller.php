<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="KEVA API Documentation",
 *     version="1.0.0",
 *     description="Documentation complète de l'API KEVA - Plateforme de e-commerce pour les entreprises gabonaises",
 *     termsOfService="https://keva.ga/terms",
 *     contact={
 *         "name": "Support KEVA",
 *         "email": "support@keva.ga",
 *         "url": "https://keva.ga/contact"
 *     },
 *     license={
 *         "name": "MIT License",
 *         "url": "https://opensource.org/licenses/MIT"
 *     }
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Serveur de développement local"
 * )
 *
 * @OA\Server(
 *     url="https://api.keva.ga",
 *     description="Serveur de production"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Authentification via token Sanctum. Format: Bearer {token}"
 * )
 *
 * @OA\Tag(
 *     name="Authentification",
 *     description="Endpoints pour l'authentification des utilisateurs"
 * )
 *
 * @OA\Tag(
 *     name="Utilisateurs",
 *     description="Gestion des profils utilisateurs"
 * )
 *
 * @OA\Tag(
 *     name="Produits",
 *     description="Gestion du catalogue de produits"
 * )
 *
 * @OA\Tag(
 *     name="Commandes",
 *     description="Gestion des commandes et du panier"
 * )
 *
 * @OA\Tag(
 *     name="Paiements",
 *     description="Intégration EBILLING pour les paiements"
 * )
 *
 * @OA\Tag(
 *     name="Factures",
 *     description="Génération et gestion des factures"
 * )
 *
 * @OA\Tag(
 *     name="Abonnements",
 *     description="Gestion des plans et abonnements"
 * )
 *
 * @OA\Tag(
 *     name="Administration",
 *     description="Endpoints réservés aux administrateurs"
 * )
 *
 * @OA\Component(
 *     @OA\Schema(
 *         schema="ApiResponse",
 *         type="object",
 *         @OA\Property(property="success", type="boolean", description="Statut de la requête"),
 *         @OA\Property(property="message", type="string", description="Message descriptif"),
 *         @OA\Property(property="data", type="object", description="Données de la réponse"),
 *         @OA\Property(property="errors", type="object", description="Erreurs de validation")
 *     )
 * )
 *
 * @OA\Component(
 *     @OA\Schema(
 *         schema="ValidationError",
 *         type="object",
 *         @OA\Property(property="success", type="boolean", example=false),
 *         @OA\Property(property="message", type="string", example="Erreur de validation"),
 *         @OA\Property(property="errors", type="object", description="Détail des erreurs par champ"),
 *         @OA\Property(property="data", type="null")
 *     )
 * )
 *
 * @OA\Component(
 *     @OA\Schema(
 *         schema="UnauthorizedError",
 *         type="object",
 *         @OA\Property(property="success", type="boolean", example=false),
 *         @OA\Property(property="message", type="string", example="Non authentifié"),
 *         @OA\Property(property="data", type="null")
 *     )
 * )
 *
 * @OA\Component(
 *     @OA\Schema(
 *         schema="NotFoundError",
 *         type="object",
 *         @OA\Property(property="success", type="boolean", example=false),
 *         @OA\Property(property="message", type="string", example="Ressource introuvable"),
 *         @OA\Property(property="data", type="null")
 *     )
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Réponse API standardisée pour les succès
     */
    protected function successResponse($data = null, $message = 'Succès', $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Réponse API standardisée pour les erreurs
     */
    protected function errorResponse($message = 'Erreur', $errors = null, $code = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Réponse API pour les erreurs de validation
     */
    protected function validationErrorResponse($errors, $message = 'Erreur de validation')
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'data' => null,
        ], 422);
    }
}
