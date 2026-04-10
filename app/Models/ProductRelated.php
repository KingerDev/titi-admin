<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductRelated extends Model
{
    protected $connection = 'titi';
    protected $table = 'titi_product_related';
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = ['product_id', 'related_id'];
}
