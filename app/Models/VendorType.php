<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorType extends Model
{
    
    protected $table = 'vendor_type';
    protected $fillable = [
        'vendor_name',
        'description',
        'vendor_type_image'
    ];

}
