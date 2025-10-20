<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCategory extends Model
{
    protected $table = 'users_category';

    protected $fillable = [
        'user_id',
        'category_id'

    ];

}
