<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'vendor_type_id',
        'category_id',
        'sub_category_id',
        'companies_id',
        'company_product_categories_id',
        'first_name',
        'middle_name',
        'last_name',
        'business_name',
        'business_type',
        'business_description',
        'business_license',
        'business_logo',
        'business_status',
        'business_start_time',
        'business_end_time',
        'business_start_day',
        'business_end_day',
        'email',
        'password',
        'web_token',
        'token',
        'otp',
        'phone_number',
        'address',
        'city',
        'state',
        'country',
        'zipcode',
        'profile_image',
        'facebook_auth_id',
        'google_auth_id',
        'apple_auth_id',
        'remember_token',
        'forgot_password_token',
        'reset_password_token',
        'status',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'forgot_password_token',
        'reset_password_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    protected function product()
    {
        return $this->hasMany(Product::class);
    }




    public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}

    /**
     * Get the role of the user.
     */
    // public function role(): BelongsTo
    // {
    //     return $this->belongsTo(Role::class);
    // }
// In App\Models\User.php
// public function products()
// {
//     return $this->hasMany(Product::class, 'vendor_id'); // Assuming 'vendor_id' is the foreign key
// }







public function products()
{
    return $this->hasMany(\App\Models\Product::class, 'user_id');
}

public function company()
{
    return $this->hasOne(\App\Models\Company::class, 'user_id');
}



}
