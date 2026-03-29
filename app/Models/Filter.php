<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Filter extends Model
{
    protected $connection = 'titi';
    protected $table = 'titi_filter';
    protected $primaryKey = 'filter_id';
    public $timestamps = false;

    public function group(): BelongsTo
    {
        return $this->belongsTo(FilterGroup::class, 'filter_group_id', 'filter_group_id');
    }

    public function description(): HasOne
    {
        return $this->hasOne(FilterDescription::class, 'filter_id', 'filter_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'titi_product_filter', 'filter_id', 'product_id');
    }
}
