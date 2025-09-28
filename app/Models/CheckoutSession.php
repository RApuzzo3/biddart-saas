<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutSession extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'bidder_id',
        'session_id',
        'subtotal',
        'tax_amount',
        'platform_fee',
        'payment_processing_fee',
        'total_amount',
        'status',
        'payment_method',
        'square_payment_id',
        'square_receipt_url',
        'payment_details',
        'items',
        'processed_by',
        'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'payment_processing_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_details' => 'array',
        'items' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the checkout session.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the event that owns the checkout session.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the bidder that owns the checkout session.
     */
    public function bidder(): BelongsTo
    {
        return $this->belongsTo(Bidder::class);
    }

    /**
     * Get the user who processed the checkout.
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'processed_by');
    }

    /**
     * Check if checkout is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if checkout is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if checkout is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if checkout failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if checkout was refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    /**
     * Mark checkout as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Mark all associated bids as paid
        if ($this->items) {
            foreach ($this->items as $item) {
                if (isset($item['bid_id'])) {
                    Bid::find($item['bid_id'])?->update(['is_paid' => true]);
                }
            }
        }
    }

    /**
     * Calculate platform fee based on event settings.
     */
    public static function calculatePlatformFee(Event $event, float $subtotal): float
    {
        $percentageFee = ($subtotal * $event->transaction_fee_percentage) / 100;
        return $percentageFee + $event->fixed_transaction_fee;
    }

    /**
     * Calculate payment processing fee (Square fees).
     */
    public static function calculateProcessingFee(float $total): float
    {
        // Square fees: 2.6% + $0.10 for card-present transactions
        return ($total * 0.026) + 0.10;
    }

    /**
     * Generate unique session ID.
     */
    public static function generateSessionId(): string
    {
        return 'cs_' . uniqid() . '_' . time();
    }

    /**
     * Get formatted total amount.
     */
    public function getFormattedTotalAttribute(): string
    {
        return '$' . number_format($this->total_amount, 2);
    }

    /**
     * Get formatted platform fee.
     */
    public function getFormattedPlatformFeeAttribute(): string
    {
        return '$' . number_format($this->platform_fee, 2);
    }
}
