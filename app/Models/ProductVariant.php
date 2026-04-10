<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $connection = 'titi';
    protected $table = 'titi_product_variant';
    protected $primaryKey = 'product_variant_id';
    public $timestamps = false;
    protected $fillable = ['product_id', 'variant_id'];
}
