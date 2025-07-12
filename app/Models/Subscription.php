<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * @OA\Schema(
 *     schema="Subscription",
 *     type="object",
 *     title="Abonnement",
 *     @OA\Property(property="id", type="integer", example=401),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="plan_id", type="integer", example=2),
 *     @OA\Property(property="subscription_id", type="string", example="SUB-2025-000123"),
 *     @OA\Property(property="status", type="string", enum={"active", "pending", "cancelled", "expired", "suspended"}, example="active"),
 *     @OA\Property(property="starts_at", type="string", format="date-time", example="2025-07-10T08:00:00Z"),
 *     @OA\Property(property="ends_at", type="string", format="date-time", example="2025-08-10T08:00:00Z"),
 *     @OA\Property(property="trial_ends_at", type="string", format="date-time", nullable=true, example="2025-07-17T08:00:00Z"),
 *     @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true, example="2025-07-20T08:00:00Z"),
 *     @OA\Property(property="amount", type="number", format="float", example=15000),
 *     @OA\Property(property="currency", type="string", example="XAF"),
 *     @OA\Property(property="auto_renew", type="boolean", example=true),
 *     @OA\Property(property="features_snapshot", type="array", @OA\Items(type="string"), example={"analytics", "support"}),
 *     @OA\Property(property="metadata", type="object", example={"origin": "mobile_app"}),
 *     @OA\Property(property="formatted_amount", type="string", readOnly=true, example="15 000 XAF"),
 *     @OA\Property(property="status_badge", type="object", readOnly=true, example={"color": "green", "text": "Actif"}),
 *     @OA\Property(property="days_remaining", type="integer", readOnly=true, example=30),
 *     @OA\Property(property="is_active", type="boolean", readOnly=true, example=true),
 *     @OA\Property(property="is_expired", type="boolean", readOnly=true, example=false),
 *     @OA\Property(property="is_expiring_soon", type="boolean", readOnly=true, example=false),
 *     @OA\Property(property="is_in_trial", type="boolean", readOnly=true, example=true),
 *     @OA\Property(property="trial_days_remaining", type="integer", nullable=true, readOnly=true, example=7),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-07-10T08:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-07-10T08:00:00Z")
 * )
 */
class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'subscription_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancelled_at',
        'amount',
        'currency',
        'auto_renew',
        'features_snapshot',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
        'features_snapshot' => 'array',
        'metadata' => 'array',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    public function scopeExpired(Builder $query): void
    {
        $query->where('ends_at', '<', now());
    }

    public function scopeExpiringSoon(Builder $query, int $days = 7): void
    {
        $query->where('status', 'active')
            ->whereBetween('ends_at', [now(), now()->addDays($days)]);
    }

    public function scopeByStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 0, ',', ' ') . ' ' . $this->currency;
    }

    public function getStatusBadgeAttribute(): array
    {
        $badges = [
            'active' => ['color' => 'green', 'text' => 'Actif'],
            'pending' => ['color' => 'yellow', 'text' => 'En attente'],
            'cancelled' => ['color' => 'red', 'text' => 'Annulé'],
            'expired' => ['color' => 'gray', 'text' => 'Expiré'],
            'suspended' => ['color' => 'orange', 'text' => 'Suspendu'],
        ];

        return $badges[$this->status] ?? ['color' => 'gray', 'text' => 'Inconnu'];
    }

    public function getDaysRemainingAttribute(): int
    {
        return max(0, now()->diffInDays($this->ends_at, false));
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active' &&
            $this->starts_at <= now() &&
            $this->ends_at > now();
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->ends_at < now();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->is_active && $this->days_remaining <= 7;
    }

    public function getIsInTrialAttribute(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at > now();
    }

    public function getTrialDaysRemainingAttribute(): ?int
    {
        if (!$this->trial_ends_at || $this->trial_ends_at <= now()) {
            return null;
        }

        return now()->diffInDays($this->trial_ends_at, false);
    }

    // Méthodes utilitaires
    public function generateSubscriptionId(): string
    {
        return 'SUB-' . date('Y') . '-' . str_pad(
            static::whereYear('created_at', date('Y'))->count() + 1,
            6,
            '0',
            STR_PAD_LEFT
        );
    }

    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'starts_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'auto_renew' => false,
        ]);
    }

    public function suspend(): void
    {
        $this->update(['status' => 'suspended']);
    }

    public function renew(int $durationDays = null): void
    {
        $plan = $this->plan;
        $duration = $durationDays ?? $plan->duration_days;

        $this->update([
            'status' => 'active',
            'starts_at' => $this->ends_at,
            'ends_at' => $this->ends_at->addDays($duration),
        ]);
    }

    public function extend(int $days): void
    {
        $this->update([
            'ends_at' => $this->ends_at->addDays($days),
        ]);
    }

    public function hasFeature(string $feature): bool
    {
        $features = $this->features_snapshot ?? $this->plan->features ?? [];
        return in_array($feature, $features);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subscription) {
            if (empty($subscription->subscription_id)) {
                $subscription->subscription_id = $subscription->generateSubscriptionId();
            }
        });
    }
}
