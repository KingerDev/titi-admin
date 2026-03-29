<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $connection = 'titi';
    protected $table = 'titi_product';
    protected $primaryKey = 'product_id';
    public $timestamps = false;

    public function description(): HasOne
    {
        return $this->hasOne(ProductDescription::class, 'product_id', 'product_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'product_id')
            ->orderBy('sort_order');
    }

    public function mainImage(): HasOne
    {
        return $this->hasOne(ProductImage::class, 'product_id', 'product_id')
            ->where('main', 1);
    }

    public function filters(): BelongsToMany
    {
        return $this->belongsToMany(Filter::class, 'titi_product_filter', 'product_id', 'filter_id');
    }
}
