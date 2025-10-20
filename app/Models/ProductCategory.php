<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    protected $table = 'company_product_categories';
    protected $fillable = [
        'user_id',
        'category_id',
        'sub_category_id',
        'company_id',
        'product_categories_name',
        'description',
        'product_categories_image',
        'status',
    ];
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }


    public function companyLinks()
    {
        return $this->hasMany(CompanyProductCategoryLinks::class, 'com_pro_cat_id');
    }
}
