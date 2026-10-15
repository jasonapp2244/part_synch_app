<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BoostedProduct extends Model
{
    use HasFactory;

    protected $table = 'boosted_products';
    
    protected $fillable = [
        'vendor_boost_id',
        'product_id',
        'boost_start',
        'boost_end',
        'is_active'
    ];

    protected $casts = [
        'boost_start' => 'datetime',
        'boost_end' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Showing to buyers right now: inside its window, and its boost approved,
     * paid and not cancelled. Optionally limited to one position.
     */
    public function scopeLive($query, ?int $positionId = null)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where('boost_start', '<=', $now)
            ->where('boost_end', '>', $now)
            ->whereHas('vendorBoost', function ($q) use ($positionId) {
                $q->approvedAndPaid();
                if ($positionId) {
                    $q->where('boost_position_id', $positionId);
                }
            });
    }

    public function vendorBoost()
    {
        return $this->belongsTo(VendorBoost::class, 'vendor_boost_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

