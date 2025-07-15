<?php
// app/Http/Controllers/Api/InvoiceController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceRequest;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\InvoiceService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    use ApiResponseTrait;

    private InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * @OA\Get(
     *     path="/api/invoices",
     *     tags={"Factures"},
     *     summary="Lister les factures de l'utilisateur",
     *     description="Récupérer la liste des factures de l'utilisateur connecté avec filtres",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"invoice", "quote", "receipt", "refund"}),
     *         description="Filtrer par type de facture"
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"draft", "sent", "paid", "overdue", "cancelled"}),
     *         description="Filtrer par statut"
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="date"),
     *         description="Date de début (YYYY-MM-DD)"
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="date"),
     *         description="Date de fin (YYYY-MM-DD)"
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         description="Recherche par numéro de facture ou nom client"
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, maximum=50),
     *         description="Nombre de factures par page"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des factures récupérée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Factures récupérées avec succès"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Invoice")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $filters = $request->only(['type', 'status', 'date_from', 'date_to', 'search']);
            $perPage = min($request->get('per_page', 10), 50);

            $query = Invoice::where('user_id', $user->id)
                ->with(['order', 'payment'])
                ->latest();

            // Filtrer par type
            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            // Filtrer par statut
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            // Filtrer par période
            if (!empty($filters['date_from'])) {
                $query->whereDate('issue_date', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('issue_date', '<=', $filters['date_to']);
            }

            // Recherche
            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereJsonContains('client_details->name', $search)
                        ->orWhereJsonContains('client_details->business_name', $search);
                });
            }

            $invoices = $query->paginate($perPage);

            // Transformer les données
            $invoicesData = $invoices->getCollection()->map(function ($invoice) {
                return $this->transformInvoice($invoice);
            });

            return $this->paginatedResponse(
                $invoices->setCollection($invoicesData),
                'Factures récupérées avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Error fetching invoices', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la récupération des factures', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/invoices",
     *     tags={"Factures"},
     *     summary="Créer une nouvelle facture",
     *     description="Créer une facture manuellement ou à partir d'une commande",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", enum={"invoice", "quote", "receipt"}, example="invoice"),
     *             @OA\Property(property="order_id", type="integer", nullable=true, example=101),
     *             @OA\Property(property="payment_id", type="integer", nullable=true, example=501),
     *             @OA\Property(
     *                 property="client_details",
     *                 type="object",
     *                 required={"name", "email"},
     *                 @OA\Property(property="name", type="string", example="Jean Mabiala"),
     *                 @OA\Property(property="email", type="string", example="jean@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+241123456789"),
     *                 @OA\Property(property="address", type="string", example="123 Rue de la Paix"),
     *                 @OA\Property(property="city", type="string", example="Libreville"),
     *                 @OA\Property(property="business_name", type="string", example="Entreprise Mabiala")
     *             ),
     *             @OA\Property(
     *                 property="line_items",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Produit A"),
     *                     @OA\Property(property="description", type="string", example="Description du produit"),
     *                     @OA\Property(property="quantity", type="integer", example=2),
     *                     @OA\Property(property="unit_price", type="number", example=25000),
     *                     @OA\Property(property="total", type="number", example=50000)
     *                 )
     *             ),
     *             @OA\Property(property="due_date", type="string", format="date", example="2025-08-10"),
     *             @OA\Property(property="notes", type="string", example="Merci pour votre commande"),
     *             @OA\Property(property="terms", type="string", example="Paiement à 30 jours")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Facture créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Facture créée avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Invoice")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:invoice,quote,receipt',
            'order_id' => 'nullable|exists:orders,id',
            'payment_id' => 'nullable|exists:payments,id',
            'client_details' => 'required|array',
            'client_details.name' => 'required|string|max:255',
            'client_details.email' => 'required|email',
            'client_details.phone' => 'nullable|string',
            'client_details.address' => 'nullable|string',
            'client_details.city' => 'nullable|string',
            'client_details.business_name' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.name' => 'required|string',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.total' => 'required|numeric|min:0',
            'due_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
        ]);

        try {
            $user = $request->user();
            $data = $request->validated();

            // Créer la facture selon le type
            if ($data['order_id']) {
                $order = Order::findOrFail($data['order_id']);

                // Vérifier que l'utilisateur est propriétaire de la commande
                if ($order->user_id !== $user->id) {
                    return $this->forbiddenResponse('Vous n\'avez pas accès à cette commande');
                }

                $invoice = $this->invoiceService->createInvoiceFromOrder($order, $data['type']);
            } elseif ($data['payment_id']) {
                $payment = Payment::findOrFail($data['payment_id']);

                // Vérifier que l'utilisateur est propriétaire du paiement
                if ($payment->user_id !== $user->id) {
                    return $this->forbiddenResponse('Vous n\'avez pas accès à ce paiement');
                }

                $invoice = $this->invoiceService->createInvoiceFromPayment($payment, $data['type']);
            } else {
                // Créer une facture manuelle
                $invoice = $this->createManualInvoice($user, $data);
            }

            // Mettre à jour avec les données personnalisées
            $updateData = [];
            if (isset($data['due_date'])) {
                $updateData['due_date'] = $data['due_date'];
            }
            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }
            if (isset($data['terms'])) {
                $updateData['terms'] = $data['terms'];
            }

            if (!empty($updateData)) {
                $invoice->update($updateData);
            }

            // Générer le PDF
            $this->invoiceService->generateInvoicePdf($invoice);

            Log::info('Invoice created', [
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
                'type' => $data['type']
            ]);

            return $this->createdResponse(
                $this->transformInvoiceDetail($invoice->fresh()),
                'Facture créée avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Error creating invoice', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la création de la facture', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/{invoice}",
     *     tags={"Factures"},
     *     summary="Détails d'une facture",
     *     description="Récupérer les détails complets d'une facture",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="invoice",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la facture"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de la facture",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Facture récupérée avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Invoice")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Facture non trouvée",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     )
     * )
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            // Vérifier que l'utilisateur a accès à cette facture
            if ($invoice->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                return $this->forbiddenResponse('Vous n\'avez pas accès à cette facture');
            }

            // Charger les relations
            $invoice->load(['order', 'payment', 'user']);

            return $this->successResponse(
                $this->transformInvoiceDetail($invoice),
                'Facture récupérée avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Error fetching invoice', [
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la récupération de la facture', null, 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/invoices/{invoice}",
     *     tags={"Factures"},
     *     summary="Mettre à jour une facture",
     *     description="Modifier une facture existante (uniquement si statut = draft)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="invoice",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la facture"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="client_details", type="object"),
     *             @OA\Property(property="line_items", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="due_date", type="string", format="date"),
     *             @OA\Property(property="notes", type="string"),
     *             @OA\Property(property="terms", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Facture mise à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Facture mise à jour avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Invoice")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Facture ne peut pas être modifiée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Seules les factures en brouillon peuvent être modifiées")
     *         )
     *     )
     * )
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        // Vérifier l'accès
        if ($invoice->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('Vous n\'avez pas accès à cette facture');
        }

        // Vérifier que la facture peut être modifiée
        if ($invoice->status !== 'draft') {
            return $this->errorResponse('Seules les factures en brouillon peuvent être modifiées', null, 400);
        }

        $request->validate([
            'client_details' => 'nullable|array',
            'line_items' => 'nullable|array',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
        ]);

        try {
            $updateData = $request->only(['client_details', 'line_items', 'due_date', 'notes', 'terms']);

            // Recalculer les totaux si les line_items ont changé
            if (isset($updateData['line_items'])) {
                $totals = $this->calculateInvoiceTotals($updateData['line_items']);
                $updateData = array_merge($updateData, $totals);
            }

            $invoice->update($updateData);

            // Régénérer le PDF
            $this->invoiceService->generateInvoicePdf($invoice);

            Log::info('Invoice updated', [
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id
            ]);

            return $this->successResponse(
                $this->transformInvoiceDetail($invoice->fresh()),
                'Facture mise à jour avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Error updating invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la mise à jour de la facture', null, 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/invoices/{invoice}",
     *     tags={"Factures"},
     *     summary="Supprimer une facture",
     *     description="Supprimer une facture (uniquement si statut = draft)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="invoice",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la facture"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Facture supprimée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Facture supprimée avec succès")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Facture ne peut pas être supprimée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Seules les factures en brouillon peuvent être supprimées")
     *         )
     *     )
     * )
     */
    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        // Vérifier l'accès
        if ($invoice->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('Vous n\'avez pas accès à cette facture');
        }

        // Vérifier que la facture peut être supprimée
        if ($invoice->status !== 'draft') {
            return $this->errorResponse('Seules les factures en brouillon peuvent être supprimées', null, 400);
        }

        try {
            // Supprimer le fichier PDF s'il existe
            if ($invoice->pdf_path && Storage::disk('public')->exists($invoice->pdf_path)) {
                Storage::disk('public')->delete($invoice->pdf_path);
            }

            $invoice->delete();

            Log::info('Invoice deleted', [
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id
            ]);

            return $this->deletedResponse('Facture supprimée avec succès');
        } catch (\Exception $e) {
            Log::error('Error deleting invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la suppression de la facture', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/{invoice}/pdf",
     *     tags={"Factures"},
     *     summary="Télécharger le PDF d'une facture",
     *     description="Télécharger le fichier PDF d'une facture",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="invoice",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la facture"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PDF téléchargé",
     *         @OA\MediaType(
     *             mediaType="application/pdf"
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PDF non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="PDF non trouvé")
     *         )
     *     )
     * )
     */
    public function downloadPdf(Request $request, Invoice $invoice)
    {
        // Vérifier l'accès
        if ($invoice->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Vous n\'avez pas accès à cette facture');
        }

        try {
            // Générer le PDF si il n'existe pas
            if (!$invoice->pdf_path || !Storage::disk('public')->exists($invoice->pdf_path)) {
                $this->invoiceService->generateInvoicePdf($invoice);
                $invoice->refresh();
            }

            $pdfPath = Storage::disk('public')->path($invoice->pdf_path);

            if (!file_exists($pdfPath)) {
                return $this->notFoundResponse('PDF non trouvé');
            }

            // Nom du fichier pour le téléchargement
            $filename = "Facture_{$invoice->invoice_number}.pdf";

            Log::info('Invoice PDF downloaded', [
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id
            ]);

            return response()->download($pdfPath, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            Log::error('Error downloading invoice PDF', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors du téléchargement du PDF', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/invoices/{invoice}/send",
     *     tags={"Factures"},
     *     summary="Envoyer une facture par email",
     *     description="Envoyer une facture par email au client",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="invoice",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la facture"
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="email", type="string", example="client@example.com"),
     *             @OA\Property(property="subject", type="string", example="Votre facture KEVA"),
     *             @OA\Property(property="message", type="string", example="Veuillez trouver ci-joint votre facture.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Facture envoyée par email",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Facture envoyée par email avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="sent_to", type="string", example="client@example.com"),
     *                 @OA\Property(property="sent_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function sendByEmail(Request $request, Invoice $invoice): JsonResponse
    {
        // Vérifier l'accès
        if ($invoice->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('Vous n\'avez pas accès à cette facture');
        }

        $request->validate([
            'email' => 'nullable|email',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:1000',
        ]);

        try {
            $email = $request->get('email', $invoice->client_details['email'] ?? null);

            if (!$email) {
                return $this->errorResponse('Adresse email du client non trouvée', null, 400);
            }

            $options = [
                'subject' => $request->get('subject'),
                'message' => $request->get('message'),
            ];

            $success = $this->invoiceService->sendInvoiceByEmail($invoice, $options);

            if ($success) {
                Log::info('Invoice sent by email', [
                    'invoice_id' => $invoice->id,
                    'email' => $email,
                    'user_id' => $request->user()->id
                ]);

                return $this->successResponse([
                    'sent_to' => $email,
                    'sent_at' => now()->toISOString(),
                ], 'Facture envoyée par email avec succès');
            } else {
                return $this->errorResponse('Erreur lors de l\'envoi de l\'email', null, 500);
            }
        } catch (\Exception $e) {
            Log::error('Error sending invoice by email', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de l\'envoi de l\'email', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/invoices/{invoice}/mark-paid",
     *     tags={"Factures"},
     *     summary="Marquer une facture comme payée",
     *     description="Marquer manuellement une facture comme payée",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="invoice",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la facture"
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="paid_at", type="string", format="date-time", example="2025-07-15T14:30:00Z"),
     *             @OA\Property(property="payment_method", type="string", example="bank_transfer"),
     *             @OA\Property(property="reference", type="string", example="REF123456"),
     *             @OA\Property(property="notes", type="string", example="Paiement reçu par virement")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Facture marquée comme payée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Facture marquée comme payée"),
     *             @OA\Property(property="data", ref="#/components/schemas/Invoice")
     *         )
     *     )
     * )
     */
    public function markAsPaid(Request $request, Invoice $invoice): JsonResponse
    {
        // Vérifier l'accès
        if ($invoice->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('Vous n\'avez pas accès à cette facture');
        }

        if ($invoice->status === 'paid') {
            return $this->errorResponse('Cette facture est déjà marquée comme payée', null, 400);
        }

        $request->validate([
            'paid_at' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $paymentData = [
                'paid_at' => $request->get('paid_at', now()),
                'payment_method' => $request->get('payment_method'),
                'reference' => $request->get('reference'),
                'notes' => $request->get('notes'),
            ];

            $success = $this->invoiceService->markInvoiceAsPaid($invoice, $paymentData);

            if ($success) {
                Log::info('Invoice marked as paid', [
                    'invoice_id' => $invoice->id,
                    'user_id' => $request->user()->id
                ]);

                return $this->successResponse(
                    $this->transformInvoiceDetail($invoice->fresh()),
                    'Facture marquée comme payée avec succès'
                );
            } else {
                return $this->errorResponse('Erreur lors de la mise à jour du statut', null, 500);
            }
        } catch (\Exception $e) {
            Log::error('Error marking invoice as paid', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la mise à jour du statut', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/stats",
     *     tags={"Factures"},
     *     summary="Statistiques des factures",
     *     description="Récupérer les statistiques de facturation de l'utilisateur",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"today", "week", "month", "year"}, default="month"),
     *         description="Période des statistiques"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques récupérées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statistiques récupérées"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_invoices", type="integer", example=45),
     *                 @OA\Property(property="sent_invoices", type="integer", example=38),
     *                 @OA\Property(property="paid_invoices", type="integer", example=35),
     *                 @OA\Property(property="overdue_invoices", type="integer", example=2),
     *                 @OA\Property(property="total_amount_invoiced", type="number", example=850000),
     *                 @OA\Property(property="total_amount_paid", type="number", example=720000),
     *                 @OA\Property(property="outstanding_amount", type="number", example=130000),
     *                 @OA\Property(property="average_payment_time", type="number", example=12.5),
     *                 @OA\Property(property="by_type", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $period = $request->get('period', 'month');

            // Définir la période
            $dateFrom = match ($period) {
                'today' => now()->startOfDay(),
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                'year' => now()->startOfYear(),
                default => now()->startOfMonth(),
            };

            $stats = $this->invoiceService->getUserInvoiceStats($user);

            // Ajouter les statistiques par période
            $periodStats = $this->getPeriodStats($user, $dateFrom);
            $stats = array_merge($stats, $periodStats);

            return $this->successResponse($stats, 'Statistiques récupérées avec succès');
        } catch (\Exception $e) {
            Log::error('Error fetching invoice stats', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la récupération des statistiques', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/invoices/{invoice}/duplicate",
     *     tags={"Factures"},
     *     summary="Dupliquer une facture",
     *     description="Créer une copie d'une facture existante",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="invoice",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la facture à dupliquer"
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", enum={"invoice", "quote"}, example="invoice"),
     *             @OA\Property(property="issue_date", type="string", format="date", example="2025-07-15"),
     *             @OA\Property(property="due_date", type="string", format="date", example="2025-08-15")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Facture dupliquée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Facture dupliquée avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Invoice")
     *         )
     *     )
     * )
     */
    public function duplicate(Request $request, Invoice $invoice): JsonResponse
    {
        // Vérifier l'accès
        if ($invoice->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('Vous n\'avez pas accès à cette facture');
        }

        $request->validate([
            'type' => 'nullable|in:invoice,quote',
            'issue_date' => 'nullable|date',
            'due_date' => 'nullable|date|after:issue_date',
        ]);

        try {
            $user = $request->user();
            $data = $invoice->toArray();

            // Modifier les champs pour la duplication
            unset($data['id'], $data['invoice_number'], $data['created_at'], $data['updated_at']);
            unset($data['sent_at'], $data['paid_at'], $data['pdf_path']);

            $data['type'] = $request->get('type', $data['type']);
            $data['status'] = 'draft';
            $data['issue_date'] = $request->get('issue_date', now()->toDateString());
            $data['due_date'] = $request->get('due_date', now()->addDays(30)->toDateString());

            $duplicatedInvoice = Invoice::create($data);

            // Générer le PDF
            $this->invoiceService->generateInvoicePdf($duplicatedInvoice);

            Log::info('Invoice duplicated', [
                'original_invoice_id' => $invoice->id,
                'new_invoice_id' => $duplicatedInvoice->id,
                'user_id' => $user->id
            ]);

            return $this->createdResponse(
                $this->transformInvoiceDetail($duplicatedInvoice->fresh()),
                'Facture dupliquée avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Error duplicating invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la duplication', null, 500);
        }
    }

    /**
     * Créer une facture manuelle
     */
    private function createManualInvoice($user, array $data): Invoice
    {
        // Calculer les totaux
        $totals = $this->calculateInvoiceTotals($data['line_items']);

        return Invoice::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'status' => 'draft',
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total_amount' => $totals['total_amount'],
            'currency' => 'XAF',
            'client_details' => $data['client_details'],
            'seller_details' => $this->getSellerDetails($user),
            'line_items' => $data['line_items'],
            'issue_date' => now()->toDateString(),
            'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
            'notes' => $data['notes'] ?? null,
            'terms' => $data['terms'] ?? $this->getDefaultTerms($data['type']),
            'metadata' => [
                'created_manually' => true,
                'created_by' => $user->id,
            ],
        ]);
    }

    /**
     * Calculer les totaux d'une facture
     */
    private function calculateInvoiceTotals(array $lineItems): array
    {
        $subtotal = collect($lineItems)->sum('total');
        $taxAmount = $subtotal * 0.18; // TVA 18%
        $totalAmount = $subtotal + $taxAmount;

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Obtenir les détails du vendeur
     */
    private function getSellerDetails($user): array
    {
        return [
            'name' => $user->business_name,
            'contact_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'city' => $user->city,
            'business_type' => $user->business_type,
        ];
    }

    /**
     * Obtenir les conditions par défaut
     */
    private function getDefaultTerms(string $type): string
    {
        return match ($type) {
            'invoice' => 'Paiement à 30 jours. Frais de retard de 1% par mois après échéance.',
            'quote' => 'Devis valable 30 jours. Prix susceptibles de modifications.',
            'receipt' => 'Reçu pour paiement. Merci pour votre confiance.',
            default => 'Conditions générales disponibles sur notre site web.',
        };
    }

    /**
     * Obtenir les statistiques par période
     */
    private function getPeriodStats($user, $dateFrom): array
    {
        $periodInvoices = Invoice::where('user_id', $user->id)
            ->where('issue_date', '>=', $dateFrom->toDateString())
            ->get();

        return [
            'period_invoices' => $periodInvoices->count(),
            'period_amount' => $periodInvoices->sum('total_amount'),
            'period_paid' => $periodInvoices->where('status', 'paid')->sum('total_amount'),
            'period_outstanding' => $periodInvoices->where('status', 'sent')->sum('total_amount'),
        ];
    }

    /**
     * Transformer une facture pour l'API
     */
    private function transformInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'type' => $invoice->type,
            'type_display_name' => $invoice->type_display_name,
            'status' => $invoice->status,
            'status_badge' => $invoice->status_badge,
            'subtotal' => $invoice->subtotal,
            'tax_amount' => $invoice->tax_amount,
            'total_amount' => $invoice->total_amount,
            'formatted_total' => $invoice->formatted_total,
            'currency' => $invoice->currency,
            'client_name' => $invoice->client_details['name'] ?? 'N/A',
            'client_email' => $invoice->client_details['email'] ?? 'N/A',
            'issue_date' => $invoice->issue_date,
            'due_date' => $invoice->due_date,
            'sent_at' => $invoice->sent_at,
            'paid_at' => $invoice->paid_at,
            'is_overdue' => $invoice->is_overdue,
            'days_until_due' => $invoice->days_until_due,
            'pdf_url' => $invoice->pdf_url,
            'created_at' => $invoice->created_at,
            'updated_at' => $invoice->updated_at,
        ];
    }

    /**
     * Transformer une facture avec détails complets
     */
    private function transformInvoiceDetail(Invoice $invoice): array
    {
        $data = $this->transformInvoice($invoice);

        // Ajouter les détails complets
        $data['client_details'] = $invoice->client_details;
        $data['seller_details'] = $invoice->seller_details;
        $data['line_items'] = $invoice->line_items;
        $data['notes'] = $invoice->notes;
        $data['terms'] = $invoice->terms;
        $data['metadata'] = $invoice->metadata;

        // Ajouter les infos de commande et paiement si présentes
        if ($invoice->order) {
            $data['order'] = [
                'id' => $invoice->order->id,
                'order_number' => $invoice->order->order_number,
                'status' => $invoice->order->status,
                'total_amount' => $invoice->order->total_amount,
                'created_at' => $invoice->order->created_at,
            ];
        }

        if ($invoice->payment) {
            $data['payment'] = [
                'id' => $invoice->payment->id,
                'payment_id' => $invoice->payment->payment_id,
                'amount' => $invoice->payment->amount,
                'payment_method' => $invoice->payment->payment_method,
                'status' => $invoice->payment->status,
                'paid_at' => $invoice->payment->paid_at,
            ];
        }

        return $data;
    }
}
