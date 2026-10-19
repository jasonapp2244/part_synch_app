<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * One boost request/purchase: a vendor's products shown in one home-page
 * position for one package's duration.
 *
 * Lifecycle: requested in the app (approval_status=pending) -> paid outside
 * the app and approved by an admin (approved, payment_status=success) ->
 * scheduled until start_date, live until end_date, then expired. It can be
 * rejected while pending or cancelled at any point.
 */
class VendorBoost extends Model
{
    use HasFactory;

    public const PAYMENT_METHODS = [
        'bank_transfer' => 'Bank transfer',
        'payment_link'  => 'Payment link / card (outside the app)',
        'cash'          => 'Cash',
        'free'          => 'Free / complimentary',
        'other'         => 'Other',
    ];

    protected $fillable = [
        'vendor_id', 'package_id', 'boost_position_id', 'approval_status',
        'amount', 'currency', 'start_date', 'end_date',
        'payment_status', 'payment_method', 'transaction_id', 'payment_reference', 'paid_at',
        'approved_by', 'approved_at', 'is_active', 'metadata',
        'vendor_note', 'admin_note', 'rejection_reason',
        'live_notified_at', 'ending_notified_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'paid_at' => 'datetime',
        'approved_at' => 'datetime',
        'live_notified_at' => 'datetime',
        'ending_notified_at' => 'datetime',
        'is_active' => 'boolean',
        'amount' => 'decimal:2',
    ];

    protected $appends = ['status'];

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function package()
    {
        return $this->belongsTo(BoostPackage::class, 'package_id');
    }

    public function position()
    {
        return $this->belongsTo(BoostPosition::class, 'boost_position_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function boostedProducts()
    {
        return $this->hasMany(BoostedProduct::class, 'vendor_boost_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'boosted_products', 'vendor_boost_id', 'product_id')
            ->withPivot('boost_start', 'boost_end', 'is_active')
            ->withTimestamps();
    }

    /**
     * pending | scheduled | active | expired | rejected | cancelled
     */
    public function getStatusAttribute(): string
    {
        if ($this->approval_status !== 'approved') {
            return $this->approval_status ?? 'pending';
        }

        $now = now();
        if ($this->start_date && $this->start_date->gt($now)) {
            return 'scheduled';
        }

        return $this->is_active && $this->end_date && $this->end_date->gt($now) ? 'active' : 'expired';
    }

    /**
     * Approved and paid boosts that hold (or will hold) slots in a position.
     */
    public function scopeApprovedAndPaid(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved')
            ->where('payment_status', 'success')
            ->where('is_active', true);
    }

    /**
     * The admin "Live & scheduled" tab: paid boosts that have not ended yet.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->approvedAndPaid()
            ->where('end_date', '>', now())
            ->orderBy('start_date');
    }

    /**
     * The admin "History" tab: rejected, cancelled, and approved boosts that
     * have ended or been switched off.
     */
    public function scopeHistory(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereIn('approval_status', ['rejected', 'cancelled'])
                ->orWhere(function ($q) {
                    $q->where('approval_status', 'approved')
                        ->where(fn ($q) => $q->where('is_active', false)->orWhere('end_date', '<=', now()));
                });
        })
            ->latest('updated_at')
            ->limit(200);
    }
}
