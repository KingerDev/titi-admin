<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FilterGroup extends Model
{
    protected $connection = 'titi';
    protected $table = 'titi_filter_group';
    protected $primaryKey = 'filter_group_id';
    public $timestamps = false;

    public function filters(): HasMany
    {
        return $this->hasMany(Filter::class, 'filter_group_id', 'filter_group_id')
            ->orderBy('sort_order');
    }

    public function description(): HasOne
    {
        return $this->hasOne(FilterGroupDescription::class, 'filter_group_id', 'filter_group_id');
    }
}
