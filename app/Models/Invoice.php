<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="Invoice",
 *     type="object",
 *     title="Facture",
 *     @OA\Property(property="id", type="integer", example=101),
 *     @OA\Property(property="invoice_number", type="string", example="INV-202507-0001"),
 *     @OA\Property(property="user_id", type="integer", example=12),
 *     @OA\Property(property="order_id", type="integer", nullable=true, example=45),
 *     @OA\Property(property="payment_id", type="integer", nullable=true, example=88),
 *     @OA\Property(property="type", type="string", enum={"invoice", "quote", "receipt", "refund"}, example="invoice"),
 *     @OA\Property(property="status", type="string", enum={"draft", "sent", "paid", "cancelled"}, example="sent"),
 *     @OA\Property(property="subtotal", type="number", format="float", example=50000),
 *     @OA\Property(property="tax_amount", type="number", format="float", example=4500),
 *     @OA\Property(property="discount_amount", type="number", format="float", example=1000),
 *     @OA\Property(property="total_amount", type="number", format="float", example=53500),
 *     @OA\Property(property="currency", type="string", example="XAF"),
 *     @OA\Property(
 *         property="client_details",
 *         type="object",
 *         example={"name": "Client SARL", "email": "client@example.com", "address": "BP 100 Libreville"}
 *     ),
 *     @OA\Property(
 *         property="seller_details",
 *         type="object",
 *         example={"name": "KEVA", "email": "contact@keva.com", "address": "Libreville, Gabon"}
 *     ),
 *     @OA\Property(
 *         property="line_items",
 *         type="array",
 *         @OA\Items(type="object", example={
 *             "name": "Produit A",
 *             "quantity": 2,
 *             "unit_price": 20000,
 *             "total": 40000
 *         })
 *     ),
 *     @OA\Property(property="issue_date", type="string", format="date", example="2025-07-10"),
 *     @OA\Property(property="due_date", type="string", format="date", example="2025-07-20"),
 *     @OA\Property(property="sent_at", type="string", format="date-time", nullable=true, example="2025-07-11T10:00:00Z"),
 *     @OA\Property(property="paid_at", type="string", format="date-time", nullable=true, example="2025-07-12T14:30:00Z"),
 *     @OA\Property(property="pdf_path", type="string", nullable=true, example="invoices/2025/07/INV-202507-0001.pdf"),
 *     @OA\Property(property="pdf_url", type="string", readOnly=true, example="https://keva.test/storage/invoices/2025/07/INV-202507-0001.pdf"),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Merci pour votre commande."),
 *     @OA\Property(property="terms", type="string", nullable=true, example="Paiement sous 7 jours."),
 *     @OA\Property(property="metadata", type="object", example={"source": "web", "campaign": "été2025"}),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-07-10T08:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-07-10T10:00:00Z")
 * )
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'user_id',
        'order_id',
        'payment_id',
        'type',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'client_details',
        'seller_details',
        'line_items',
        'issue_date',
        'due_date',
        'sent_at',
        'paid_at',
        'pdf_path',
        'notes',
        'terms',
        'metadata',
    ];

    protected $casts = [
        'client_details' => 'array',
        'seller_details' => 'array',
        'line_items' => 'array',
        'issue_date' => 'date',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    // Scopes
    public function scopeByStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeByType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', 'sent')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    public function scopeDueSoon(Builder $query, int $days = 7): void
    {
        $query->where('status', 'sent')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now(), now()->addDays($days)]);
    }

    // Accessors
    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total_amount, 0, ',', ' ') . ' ' . $this->currency;
    }

    public function getStatusBadgeAttribute(): array
    {
        $badges = [
            'draft' => ['color' => 'gray', 'text' => 'Brouillon'],
            'sent' => ['color' => 'blue', 'text' => 'Envoyée'],
            'paid' => ['color' => 'green', 'text' => 'Payée'],
            'overdue' => ['color' => 'red', 'text' => 'En retard'],
            'cancelled' => ['color' => 'red', 'text' => 'Annulée'],
        ];

        $status = $this->status;
        if ($status === 'sent' && $this->is_overdue) {
            $status = 'overdue';
        }

        return $badges[$status] ?? ['color' => 'gray', 'text' => 'Inconnu'];
    }

    public function getTypeDisplayNameAttribute(): string
    {
        $types = [
            'invoice' => 'Facture',
            'quote' => 'Devis',
            'receipt' => 'Reçu',
            'refund' => 'Avoir',
        ];

        return $types[$this->type] ?? 'Inconnu';
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'sent' &&
            $this->due_date &&
            $this->due_date->isPast();
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date || $this->status !== 'sent') {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }

    public function getPdfUrlAttribute(): ?string
    {
        return $this->pdf_path ? asset('storage/' . $this->pdf_path) : null;
    }

    // Méthodes utilitaires
    public function generateInvoiceNumber(): string
    {
        $prefix = strtoupper(substr($this->type, 0, 3));
        $year = date('Y');
        $month = date('m');

        $lastInvoice = static::where('type', $this->type)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastInvoice ?
            ((int) substr($lastInvoice->invoice_number, -4)) + 1 : 1;

        return "{$prefix}-{$year}{$month}-" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function send(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function generatePdf(): string
    {
        // Cette méthode sera implémentée dans le service InvoiceService
        return '';
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = $invoice->generateInvoiceNumber();
            }

            if (empty($invoice->issue_date)) {
                $invoice->issue_date = now()->toDateString();
            }
        });
    }
}
