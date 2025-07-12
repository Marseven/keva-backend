<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // Si c'est une requête API
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * Gestion des exceptions pour les API
     */
    private function handleApiException($request, Throwable $exception)
    {
        $response = [
            'success' => false,
            'message' => 'Une erreur est survenue',
            'data' => null,
        ];

        // Validation Exception
        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $exception->errors(),
                'data' => null,
            ], 422);
        }

        // Authentication Exception
        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
                'data' => null,
            ], 401);
        }

        // Model Not Found Exception
        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Ressource introuvable',
                'data' => null,
            ], 404);
        }

        // Not Found Exception
        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint introuvable',
                'data' => null,
            ], 404);
        }

        // Method Not Allowed Exception
        if ($exception instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Méthode non autorisée',
                'data' => null,
            ], 405);
        }

        // Server Error
        if (config('app.debug')) {
            $response['message'] = $exception->getMessage();
            $response['file'] = $exception->getFile();
            $response['line'] = $exception->getLine();
            $response['trace'] = $exception->getTraceAsString();
        }

        return response()->json($response, 500);
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
                'data' => null,
            ], 401);
        }

        return redirect()->guest(route('login'));
    }
}
