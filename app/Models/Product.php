<?php

namespace App\Models;

use App\Mail\ProductBackInStockMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Product extends Model
{
    /**
     * Email everyone who asked to hear when this product came back (checkout
     * marks them 'notified' when it was out of stock) and clear their flag.
     * Mail is best-effort: the restock itself has already been saved.
     */
    public function notifyBackInStock(): int
    {
        $waiting = Wishlist::where('product_id', $this->id)->where('status', 'notified')->get();

        foreach ($waiting as $wishlist) {
            $user = User::find($wishlist->user_id);

            if ($user) {
                try {
                    Mail::to($user->email)->send(new ProductBackInStockMail($this, $user));
                } catch (\Exception $e) {
                    Log::error('Back-in-stock email failed', ['product_id' => $this->id, 'user_id' => $user->id, 'error' => $e->getMessage()]);
                }
            }

            $wishlist->status = 'active';
            $wishlist->save();
        }

        return $waiting->count();
    }

    protected $table = 'products';
    protected $fillable = [
        'user_id',
        'vendor_id',
        'category_id',
        'sub_category_id',
        'company_id',
        'company_product_categories_id',
        'service_status',
        'service_type',
        'sku',
        'barcode',
        'warranty',
        'name',
        'description',
        'price',
        'product_pic',
        'min_order_quantity',
        'max_order_quantity',
        'modal_number',
        'expire_date',
        'discount_type',
        'size_options',
        'tags',
        'return_policy',
        'installation_required',
        'installation_guide_url',
        'stock_quantity',
        'discount',
        'brand',
        'weight',
        'dimensions',
        'is_active',
        'tax_rate',
        'status',
        'is_top',
        'top_start_date',
        'top_expire_date'
    ];

    // **Service  Type Enum**
    const SERVICE_TYPES = [
        'free' => 'Free',
        'paid' => 'Paid'
    ];
    // **Service Status Type Enum**
    const SERVICE_STATUS = [
        'No' => 'N',
        'Yes' => 'Y'
    ];

    public function productImages()
    {
        return $this->hasMany(ProductImage::class);
    }

    // public function company()
    // {
    //     return $this->belongsTo(Company::class);
    // }


    public function company()
    {
        return $this->hasOne(Company::class, 'user_id');
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function companyLinks()
    {
        return $this->hasMany(CompanyProductCategoryLinks::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class, 'product_id', 'id')
            ->where('type', 'product');
    }


    public function products()
    {
        return $this->hasMany(Product::class, 'user_id');
    }



  

    public function companies()
    {
        return $this->belongsToMany(
            \App\Models\Company::class,
            'company_product_category_links',
            'product_id',
            'company_id'
        );
    }

    public function boostedProducts()
    {
        return $this->hasMany(BoostedProduct::class, 'product_id');
    }

    /**
     * Products with a boost showing right now (optionally in one position).
     * See BoostedProduct::scopeLive.
     */
    public function scopeLiveBoosted($query, ?int $positionId = null)
    {
        return $query->whereHas('boostedProducts', fn ($q) => $q->live($positionId));
    }

    public function vendorBoosts()
    {
        return $this->belongsToMany(VendorBoost::class, 'boosted_products', 'product_id', 'vendor_boost_id')
            ->withPivot('boost_start', 'boost_end', 'is_active')
            ->withTimestamps();
    }

    public function activeBoosts()
    {
        return $this->hasMany(BoostedProduct::class, 'product_id')
            ->where('is_active', true)
            ->where('boost_end', '>', now());
    }
}
