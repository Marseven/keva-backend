<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Trait pour standardiser les réponses API
 */
trait ApiResponseTrait
{
    /**
     * Réponse de succès standardisée
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    protected function successResponse($data = null, $message = 'Succès', $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Réponse d'erreur standardisée
     *
     * @param string $message
     * @param mixed $errors
     * @param int $code
     * @return JsonResponse
     */
    protected function errorResponse($message = 'Erreur', $errors = null, $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Réponse d'erreur de validation
     *
     * @param mixed $errors
     * @param string $message
     * @return JsonResponse
     */
    protected function validationErrorResponse($errors, $message = 'Erreur de validation'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'data' => null,
        ], 422);
    }

    /**
     * Réponse pour ressource créée
     *
     * @param mixed $data
     * @param string $message
     * @return JsonResponse
     */
    protected function createdResponse($data = null, $message = 'Ressource créée avec succès'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Réponse pour ressource mise à jour
     *
     * @param mixed $data
     * @param string $message
     * @return JsonResponse
     */
    protected function updatedResponse($data = null, $message = 'Ressource mise à jour avec succès'): JsonResponse
    {
        return $this->successResponse($data, $message, 200);
    }

    /**
     * Réponse pour ressource supprimée
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function deletedResponse($message = 'Ressource supprimée avec succès'): JsonResponse
    {
        return $this->successResponse(null, $message, 200);
    }

    /**
     * Réponse pour ressource non trouvée
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function notFoundResponse($message = 'Ressource introuvable'): JsonResponse
    {
        return $this->errorResponse($message, null, 404);
    }

    /**
     * Réponse pour accès non autorisé
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function unauthorizedResponse($message = 'Accès non autorisé'): JsonResponse
    {
        return $this->errorResponse($message, null, 401);
    }

    /**
     * Réponse pour accès interdit
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function forbiddenResponse($message = 'Accès interdit'): JsonResponse
    {
        return $this->errorResponse($message, null, 403);
    }

    /**
     * Réponse paginée
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $data
     * @param string $message
     * @return JsonResponse
     */
    protected function paginatedResponse($data, $message = 'Données récupérées'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'links' => [
                'first' => $data->url(1),
                'last' => $data->url($data->lastPage()),
                'prev' => $data->previousPageUrl(),
                'next' => $data->nextPageUrl(),
            ]
        ], 200);
    }
}
