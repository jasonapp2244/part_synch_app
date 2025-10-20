<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    //
    // public function products(){
    //     return $this->hasMany(Product::class);
    // }


    public function productCategories()
    {
        return $this->hasMany( productCategory::class, 'company_id');
    }


    public function user()
{
    return $this->belongsTo(\App\Models\User::class, 'user_id');
}

// public function productss()
// {
//     return $this->belongsToMany(
//         \App\Models\Product::class,
//         'company_product_category_links',
//         'company_id',
//         'product_id'
//     );
// }




// public function products()
// {
//     return $this->belongsToMany(
//         \App\Models\Product::class,
//         'company_product_category_links',
//         'company_id',
//         'product_id'
//     );
// }




public function links()
{
    return $this->hasMany(CompanyProductCategoryLinks::class);
}
}

