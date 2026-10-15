<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BoostPosition extends Model {
    use HasFactory;
    protected $fillable = ['name','priority','display_limit','price_multiplier','status'];
    protected $casts = ['status' => 'boolean', 'display_limit' => 'integer', 'price_multiplier' => 'decimal:2'];
    public function boosts(){ return $this->hasMany(VendorBoost::class,'boost_position_id'); }
}
    