<?php
// app/Services/InvoiceService.php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvoiceService
{
    /**
     * Créer une facture à partir d'une commande
     */
    public function createInvoiceFromOrder(Order $order, string $type = 'invoice'): Invoice
    {
        $invoice = Invoice::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'type' => $type,
            'status' => 'draft',
            'subtotal' => $order->subtotal,
            'tax_amount' => $order->tax_amount,
            'discount_amount' => $order->discount_amount,
            'total_amount' => $order->total_amount,
            'currency' => $order->currency,
            'client_details' => $this->extractClientDetails($order),
            'seller_details' => $this->getSellerDetails(),
            'line_items' => $this->extractLineItems($order),
            'issue_date' => now()->toDateString(),
            'due_date' => $type === 'invoice' ? now()->addDays(30)->toDateString() : null,
            'notes' => 'Merci pour votre confiance !',
            'terms' => $this->getDefaultTerms($type),
            'metadata' => [
                'order_number' => $order->order_number,
                'created_from_order' => true,
            ],
        ]);

        return $invoice;
    }

    /**
     * Créer une facture à partir d'un paiement
     */
    public function createInvoiceFromPayment(Payment $payment, string $type = 'receipt'): Invoice
    {
        $order = $payment->order;

        $invoice = Invoice::create([
            'user_id' => $payment->user_id,
            'order_id' => $order?->id,
            'payment_id' => $payment->id,
            'type' => $type,
            'status' => 'paid',
            'subtotal' => $payment->amount,
            'tax_amount' => $order?->tax_amount ?? 0,
            'discount_amount' => $order?->discount_amount ?? 0,
            'total_amount' => $payment->amount,
            'currency' => $payment->currency,
            'client_details' => $this->extractClientDetailsFromPayment($payment),
            'seller_details' => $this->getSellerDetails(),
            'line_items' => $order ? $this->extractLineItems($order) : $this->createPaymentLineItem($payment),
            'issue_date' => $payment->paid_at?->toDateString() ?? now()->toDateString(),
            'due_date' => null, // Déjà payé
            'paid_at' => $payment->paid_at,
            'notes' => 'Paiement reçu avec succès.',
            'terms' => $this->getDefaultTerms($type),
            'metadata' => [
                'payment_id' => $payment->payment_id,
                'payment_method' => $payment->payment_method,
                'created_from_payment' => true,
            ],
        ]);

        return $invoice;
    }

    /**
     * Générer le PDF d'une facture
     */
    public function generateInvoicePdf(Invoice $invoice, bool $save = true): string
    {
        try {
            $data = $this->prepareInvoiceData($invoice);

            // Générer le PDF avec DomPDF
            $pdf = Pdf::loadView('invoices.template', $data)
                ->setPaper('A4', 'portrait')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isPhpEnabled' => true,
                    'defaultFont' => 'Arial',
                    'margin_top' => 10,
                    'margin_bottom' => 10,
                    'margin_left' => 10,
                    'margin_right' => 10,
                ]);

            $pdfContent = $pdf->output();

            if ($save) {
                // Sauvegarder le PDF
                $filename = $this->generatePdfFilename($invoice);
                $path = $this->savePdfFile($filename, $pdfContent);

                // Mettre à jour la facture avec le chemin du PDF
                $invoice->update(['pdf_path' => $path]);

                Log::info('Invoice PDF generated', [
                    'invoice_id' => $invoice->id,
                    'pdf_path' => $path
                ]);

                return $path;
            }

            return $pdfContent;
        } catch (\Exception $e) {
            Log::error('Error generating invoice PDF', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Erreur lors de la génération du PDF : ' . $e->getMessage());
        }
    }

    /**
     * Envoyer une facture par email
     */
    public function sendInvoiceByEmail(Invoice $invoice, array $options = []): bool
    {
        try {
            // Générer le PDF si pas déjà fait
            if (!$invoice->pdf_path) {
                $this->generateInvoicePdf($invoice);
                $invoice->refresh();
            }

            $clientEmail = $invoice->client_details['email'] ?? null;
            if (!$clientEmail) {
                throw new \Exception('Adresse email du client non trouvée');
            }

            // Préparer les données pour l'email
            $emailData = [
                'invoice' => $invoice,
                'client_name' => $invoice->client_details['name'],
                'subject' => $options['subject'] ?? $this->getDefaultEmailSubject($invoice),
                'message' => $options['message'] ?? $this->getDefaultEmailMessage($invoice),
            ];

            // Envoyer l'email avec la facture en pièce jointe
            Mail::to($clientEmail)->send(new InvoiceMail($emailData, $invoice->pdf_path));

            // Marquer comme envoyée
            $invoice->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('Invoice sent by email', [
                'invoice_id' => $invoice->id,
                'email' => $clientEmail
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error sending invoice by email', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Marquer une facture comme payée
     */
    public function markInvoiceAsPaid(Invoice $invoice, array $paymentData = []): bool
    {
        try {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => $paymentData['paid_at'] ?? now(),
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'payment_confirmed_at' => now()->toISOString(),
                    'payment_data' => $paymentData,
                ])
            ]);

            Log::info('Invoice marked as paid', [
                'invoice_id' => $invoice->id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error marking invoice as paid', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Générer un avoir (credit note)
     */
    public function createCreditNote(Invoice $originalInvoice, array $refundData): Invoice
    {
        $creditNote = Invoice::create([
            'user_id' => $originalInvoice->user_id,
            'order_id' => $originalInvoice->order_id,
            'type' => 'refund',
            'status' => 'sent',
            'subtotal' => -$refundData['amount'],
            'tax_amount' => - ($refundData['tax_amount'] ?? 0),
            'total_amount' => -$refundData['total_amount'],
            'currency' => $originalInvoice->currency,
            'client_details' => $originalInvoice->client_details,
            'seller_details' => $originalInvoice->seller_details,
            'line_items' => $this->createRefundLineItems($refundData),
            'issue_date' => now()->toDateString(),
            'notes' => $refundData['reason'] ?? 'Avoir suite à remboursement',
            'terms' => 'Avoir - Aucun montant à payer',
            'metadata' => [
                'original_invoice_id' => $originalInvoice->id,
                'original_invoice_number' => $originalInvoice->invoice_number,
                'refund_reason' => $refundData['reason'] ?? 'Non spécifiée',
            ],
        ]);

        // Générer automatiquement le PDF
        $this->generateInvoicePdf($creditNote);

        return $creditNote;
    }

    /**
     * Obtenir les statistiques de facturation pour un utilisateur
     */
    public function getUserInvoiceStats(User $user): array
    {
        $invoices = $user->invoices();

        return [
            'total_invoices' => $invoices->count(),
            'sent_invoices' => $invoices->where('status', 'sent')->count(),
            'paid_invoices' => $invoices->where('status', 'paid')->count(),
            'overdue_invoices' => $invoices->where('status', 'sent')
                ->where('due_date', '<', now())
                ->count(),
            'total_amount_invoiced' => $invoices->where('type', 'invoice')->sum('total_amount'),
            'total_amount_paid' => $invoices->where('status', 'paid')->sum('total_amount'),
            'outstanding_amount' => $invoices->where('status', 'sent')->sum('total_amount'),
            'average_payment_time' => $this->calculateAveragePaymentTime($user),
            'by_type' => $invoices->get()->groupBy('type')->map->count(),
        ];
    }

    /**
     * Préparer les données pour le template de facture
     */
    private function prepareInvoiceData(Invoice $invoice): array
    {
        return [
            'invoice' => $invoice,
            'company' => $this->getCompanyInfo(),
            'client' => $invoice->client_details,
            'line_items' => $invoice->line_items,
            'totals' => $this->calculateTotals($invoice),
            'qr_code' => $this->generateQrCode($invoice),
            'payment_info' => $this->getPaymentInfo($invoice),
        ];
    }

    /**
     * Extraire les détails client d'une commande
     */
    private function extractClientDetails(Order $order): array
    {
        $shippingAddress = $order->shipping_address;

        return [
            'name' => $shippingAddress['name'] ?? $order->user->full_name,
            'email' => $order->customer_email ?? $order->user->email,
            'phone' => $order->customer_phone ?? $order->user->phone,
            'address' => $shippingAddress['address'] ?? '',
            'city' => $shippingAddress['city'] ?? $order->user->city,
            'postal_code' => $shippingAddress['postal_code'] ?? '',
            'country' => 'Gabon',
            'business_name' => $order->user->business_name,
        ];
    }

    /**
     * Extraire les détails client d'un paiement
     */
    private function extractClientDetailsFromPayment(Payment $payment): array
    {
        return [
            'name' => $payment->payer_name,
            'email' => $payment->payer_email ?? $payment->user->email,
            'phone' => $payment->payer_phone,
            'address' => $payment->user->address ?? '',
            'city' => $payment->user->city ?? '',
            'country' => 'Gabon',
            'business_name' => $payment->user->business_name,
        ];
    }

    /**
     * Obtenir les détails du vendeur
     */
    private function getSellerDetails(): array
    {
        return [
            'name' => 'KEVA',
            'business_name' => 'KEVA SARL',
            'email' => 'contact@keva.ga',
            'phone' => '+241 77 00 00 00',
            'address' => 'Immeuble KEVA, Boulevard Triomphal',
            'city' => 'Libreville',
            'postal_code' => 'BP 1234',
            'country' => 'Gabon',
            'tax_number' => 'GA123456789',
            'website' => 'https://keva.ga',
        ];
    }

    /**
     * Extraire les lignes d'articles d'une commande
     */
    private function extractLineItems(Order $order): array
    {
        return $order->items->map(function ($item) {
            return [
                'name' => $item->product_name,
                'description' => $item->product_snapshot['description'] ?? '',
                'sku' => $item->product_sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total_price,
                'options' => $item->product_options,
            ];
        })->toArray();
    }

    /**
     * Créer une ligne d'article pour un paiement
     */
    private function createPaymentLineItem(Payment $payment): array
    {
        return [[
            'name' => 'Paiement reçu',
            'description' => "Paiement via {$payment->method_display_name}",
            'sku' => $payment->payment_id,
            'quantity' => 1,
            'unit_price' => $payment->amount,
            'total' => $payment->amount,
        ]];
    }

    /**
     * Créer les lignes d'articles pour un avoir
     */
    private function createRefundLineItems(array $refundData): array
    {
        return [[
            'name' => 'Remboursement',
            'description' => $refundData['reason'] ?? 'Remboursement',
            'quantity' => 1,
            'unit_price' => -$refundData['amount'],
            'total' => -$refundData['total_amount'],
        ]];
    }

    /**
     * Obtenir les conditions par défaut selon le type
     */
    private function getDefaultTerms(string $type): string
    {
        return match ($type) {
            'invoice' => 'Paiement à 30 jours. Frais de retard de 1% par mois après échéance.',
            'quote' => 'Devis valable 30 jours. Prix susceptibles de modifications.',
            'receipt' => 'Reçu pour paiement. Merci pour votre confiance.',
            'refund' => 'Avoir suite à remboursement. Aucun montant à payer.',
            default => 'Conditions générales disponibles sur notre site web.',
        };
    }

    /**
     * Générer le nom du fichier PDF
     */
    private function generatePdfFilename(Invoice $invoice): string
    {
        $prefix = match ($invoice->type) {
            'invoice' => 'Facture',
            'quote' => 'Devis',
            'receipt' => 'Recu',
            'refund' => 'Avoir',
            default => 'Document',
        };

        return "{$prefix}_{$invoice->invoice_number}.pdf";
    }

    /**
     * Sauvegarder le fichier PDF
     */
    private function savePdfFile(string $filename, string $content): string
    {
        $directory = 'invoices/' . date('Y/m');
        $path = $directory . '/' . $filename;

        // Créer le répertoire si nécessaire
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        // Sauvegarder le fichier
        Storage::disk('public')->put($path, $content);

        return $path;
    }

    /**
     * Calculer les totaux pour l'affichage
     */
    private function calculateTotals(Invoice $invoice): array
    {
        return [
            'subtotal' => $invoice->subtotal,
            'tax_amount' => $invoice->tax_amount,
            'discount_amount' => $invoice->discount_amount,
            'total_amount' => $invoice->total_amount,
            'formatted_subtotal' => number_format($invoice->subtotal, 0, ',', ' ') . ' ' . $invoice->currency,
            'formatted_tax' => number_format($invoice->tax_amount, 0, ',', ' ') . ' ' . $invoice->currency,
            'formatted_discount' => number_format($invoice->discount_amount, 0, ',', ' ') . ' ' . $invoice->currency,
            'formatted_total' => number_format($invoice->total_amount, 0, ',', ' ') . ' ' . $invoice->currency,
        ];
    }

    /**
     * Générer un QR code pour le paiement
     */
    private function generateQrCode(Invoice $invoice): ?string
    {
        if ($invoice->status === 'paid') {
            return null;
        }

        // Données pour QR code de paiement Mobile Money
        $qrData = [
            'type' => 'payment',
            'amount' => $invoice->total_amount,
            'currency' => $invoice->currency,
            'reference' => $invoice->invoice_number,
            'merchant' => 'KEVA',
        ];

        // Générer le QR code (utiliser une librairie comme endroid/qr-code)
        return base64_encode(json_encode($qrData));
    }

    /**
     * Obtenir les informations de paiement
     */
    private function getPaymentInfo(Invoice $invoice): array
    {
        if ($invoice->type === 'refund' || $invoice->status === 'paid') {
            return [];
        }

        return [
            'methods' => [
                'airtel_money' => '+241 77 XXX XXX',
                'moov_money' => '+241 06 XXX XXX',
                'bank_transfer' => 'BHG - 40001-12345-67890',
            ],
            'reference' => $invoice->invoice_number,
            'due_date' => $invoice->due_date,
        ];
    }

    /**
     * Obtenir les informations de l'entreprise
     */
    private function getCompanyInfo(): array
    {
        return [
            'name' => 'KEVA',
            'logo' => 'images/logo.png',
            'address' => 'Immeuble KEVA, Boulevard Triomphal',
            'city' => 'Libreville, Gabon',
            'phone' => '+241 77 00 00 00',
            'email' => 'contact@keva.ga',
            'website' => 'https://keva.ga',
        ];
    }

    /**
     * Calculer le temps moyen de paiement
     */
    private function calculateAveragePaymentTime(User $user): ?float
    {
        $paidInvoices = $user->invoices()
            ->where('status', 'paid')
            ->whereNotNull('sent_at')
            ->whereNotNull('paid_at')
            ->get();

        if ($paidInvoices->isEmpty()) {
            return null;
        }

        $totalDays = $paidInvoices->sum(function ($invoice) {
            return $invoice->sent_at->diffInDays($invoice->paid_at);
        });

        return round($totalDays / $paidInvoices->count(), 1);
    }

    /**
     * Sujet par défaut pour l'email
     */
    private function getDefaultEmailSubject(Invoice $invoice): string
    {
        $type = match ($invoice->type) {
            'invoice' => 'Facture',
            'quote' => 'Devis',
            'receipt' => 'Reçu',
            'refund' => 'Avoir',
            default => 'Document',
        };

        return "{$type} #{$invoice->invoice_number} - KEVA";
    }

    /**
     * Message par défaut pour l'email
     */
    private function getDefaultEmailMessage(Invoice $invoice): string
    {
        $clientName = $invoice->client_details['name'];

        return match ($invoice->type) {
            'invoice' => "Bonjour {$clientName},\n\nVeuillez trouver ci-joint votre facture.\n\nCordialement,\nL'équipe KEVA",
            'quote' => "Bonjour {$clientName},\n\nVeuillez trouver ci-joint votre devis.\n\nCordialement,\nL'équipe KEVA",
            'receipt' => "Bonjour {$clientName},\n\nVeuillez trouver ci-joint votre reçu de paiement.\n\nCordialement,\nL'équipe KEVA",
            default => "Bonjour {$clientName},\n\nVeuillez trouver ci-joint votre document.\n\nCordialement,\nL'équipe KEVA",
        };
    }
}
