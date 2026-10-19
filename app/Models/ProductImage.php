<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $table = 'product_images';

    protected $fillable = [
        'user_id',
        'product_id',
        'image_url'
    ];

    // public function product()
    // {
    //     return $this->belongsTo(Product::class,'product_id');
    // }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The image's URL on this server. Uploads store an absolute URL with
     * whatever host handled the upload baked in (an emulator's 10.0.2.2, a
     * staging domain), and older rows store a bare storage path. Both are
     * re-based onto the current host so the admin panel can show them.
     */
    public function url(): ?string
    {
        $value = trim((string) $this->image_url);
        if ($value === '') {
            return null;
        }

        $position = strpos($value, '/storage/');
        $path = $position !== false
            ? substr($value, $position + strlen('/storage/'))
            : ltrim(preg_replace('#^storage/#', '', $value), '/');

        return asset('storage/' . $path);
    }
    // public function product()
    // {
    //     return $this->belongsTo(Product::class);
    // }
}
