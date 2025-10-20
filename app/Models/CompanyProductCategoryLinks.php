<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProductCategoryLinks extends Model
{
    protected $table = 'company_product_category_links';
    protected $fillable = [
        'company_id',
        'product_id',
        'com_pro_cat_id',
        'modal_number',
        'product_number',
        'custom_price',
        'original_price',
        'stock_quantity',
        'warranty',
        'discount',
        'discount_expire_date',
        'is_featured',
        'is_active',
        'created_by',
        'notes',
        'price_updated_at',
        'stock_updated_at',
        'is_deleted',
        'sku',
        'is_approved',
        'price_type',
        'tags',
    ];


    public function company()
{
    return $this->belongsTo(Company::class);
}
public function product()
{
    return $this->belongsTo(Product::class);
}


public function productCategory()
{
    return $this->belongsTo(ProductCategory::class, 'com_pro_cat_id');
}

public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}

}
