<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $table = 'services';

    protected $fillable = [
        'user_id',
        'product_id',
        'service_types',
        'title',
        'description',
        'price',
        'start_day',
        'end_day',
        'start_time',
        'end_time',
        'duration_type',
        'is_recurring',
        'service_mode',
        'status'
    ];
    // protected $casts = [
    //     'title'        => 'array',
    //     'description'  => 'array',
    //     'start_day'    => 'date',
    //     'end_day'      => 'date',
    //     'start_time'   => 'datetime:H:i',
    //     'end_time'     => 'datetime:H:i',
    //     'is_recurring' => 'boolean',
    // ];

   // **Service  Type Enum**
   const SERVICE_TYPES = [
    'quarterly'=>'quarterly',
    'semi_annually'=>'semi_annually',
    'three_quarters'=>'three_quarters',
    'annually'=>'annually',
   ];

   // **Duration  Type Enum**
   const DURATION_TYPES = [
    'weekly'=>'weekly',
    'monthly'=>'monthly',
    'yearly'=>'yearly'
];
}
