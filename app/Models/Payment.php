<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="Payment",
 *     type="object",
 *     title="Paiement",
 *     @OA\Property(property="id", type="integer", example=501),
 *     @OA\Property(property="payment_id", type="string", example="PAY-20250710-ABCDEF12"),
 *     @OA\Property(property="user_id", type="integer", example=12),
 *     @OA\Property(property="order_id", type="integer", example=101),
 *     @OA\Property(property="transaction_id", type="string", example="TX1234567890"),
 *     @OA\Property(property="bill_id", type="string", example="BILL-789456123"),
 *     @OA\Property(property="external_reference", type="string", example="EXT-REF-001"),
 *     @OA\Property(property="amount", type="number", format="float", example=56000),
 *     @OA\Property(property="currency", type="string", example="XAF"),
 *     @OA\Property(
 *         property="gateway_response",
 *         type="object",
 *         example={"status": "OK", "code": 200, "transaction_ref": "ABC123"}
 *     ),
 *     @OA\Property(property="payment_method", type="string", enum={"airtel_money", "moov_money", "visa_mastercard", "bank_transfer", "cash", "other"}, example="visa_mastercard"),
 *     @OA\Property(property="payment_provider", type="string", example="Ebilling"),
 *     @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed", "cancelled", "refunded"}, example="completed"),
 *     @OA\Property(property="payer_name", type="string", example="Jean Mabiala"),
 *     @OA\Property(property="payer_email", type="string", format="email", example="jean@example.com"),
 *     @OA\Property(property="payer_phone", type="string", example="+241123456789"),
 *     @OA\Property(property="failure_reason", type="string", nullable=true, example="Carte refusée"),
 *     @OA\Property(property="paid_at", type="string", format="date-time", nullable=true, example="2025-07-10T12:00:00Z"),
 *     @OA\Property(property="failed_at", type="string", format="date-time", nullable=true, example="2025-07-10T13:00:00Z"),
 *     @OA\Property(property="refunded_at", type="string", format="date-time", nullable=true, example="2025-07-11T09:00:00Z"),
 *     @OA\Property(property="metadata", type="object", example={"channel": "web", "source": "checkout"}),
 *     @OA\Property(property="formatted_amount", type="string", readOnly=true, example="56 000 XAF"),
 *     @OA\Property(property="is_successful", type="boolean", readOnly=true, example=true),
 *     @OA\Property(property="can_be_refunded", type="boolean", readOnly=true, example=true),
 *     @OA\Property(property="method_display_name", type="string", readOnly=true, example="Visa/Mastercard"),
 *     @OA\Property(property="status_badge", type="object", readOnly=true, example={"color": "green", "text": "Terminé"}),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-07-10T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-07-10T12:30:00Z")
 * )
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'payment_id',
        'transaction_id',
        'bill_id',
        'external_reference',
        'amount',
        'currency',
        'payment_method',
        'payment_provider',
        'status',
        'payer_name',
        'payer_email',
        'payer_phone',
        'gateway_response',
        'metadata',
        'failure_reason',
        'paid_at',
        'failed_at',
        'refunded_at',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
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

    // Scopes
    public function scopeByStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', 'completed');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function scopeFailed(Builder $query): void
    {
        $query->where('status', 'failed');
    }

    public function scopeByMethod(Builder $query, string $method): void
    {
        $query->where('payment_method', $method);
    }

    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 0, ',', ' ') . ' ' . $this->currency;
    }

    public function getStatusBadgeAttribute(): array
    {
        $badges = [
            'pending' => ['color' => 'yellow', 'text' => 'En attente'],
            'processing' => ['color' => 'blue', 'text' => 'En cours'],
            'completed' => ['color' => 'green', 'text' => 'Terminé'],
            'failed' => ['color' => 'red', 'text' => 'Échec'],
            'cancelled' => ['color' => 'gray', 'text' => 'Annulé'],
            'refunded' => ['color' => 'purple', 'text' => 'Remboursé'],
        ];

        return $badges[$this->status] ?? ['color' => 'gray', 'text' => 'Inconnu'];
    }

    public function getMethodDisplayNameAttribute(): string
    {
        $methods = [
            'airtel_money' => 'Airtel Money',
            'moov_money' => 'Moov Money',
            'visa_mastercard' => 'Visa/Mastercard',
            'bank_transfer' => 'Virement bancaire',
            'cash' => 'Espèces',
            'other' => 'Autre',
        ];

        return $methods[$this->payment_method] ?? 'Inconnu';
    }

    public function getIsSuccessfulAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getCanBeRefundedAttribute(): bool
    {
        return $this->status === 'completed' && is_null($this->refunded_at);
    }

    // Méthodes utilitaires
    public function generatePaymentId(): string
    {
        return 'PAY-' . date('Ymd') . '-' . strtoupper(Str::random(8));
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // Mettre à jour la commande si elle existe
        if ($this->order) {
            $this->order->markAsPaid();
        }
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    public function markAsRefunded(): void
    {
        $this->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);

        // Mettre à jour la commande si elle existe
        if ($this->order) {
            $this->order->update(['payment_status' => 'refunded']);
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->payment_id)) {
                $payment->payment_id = $payment->generatePaymentId();
            }
        });
    }
}
