<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Category extends Model
{
    protected $connection = 'titi';
    protected $table      = 'titi_category';
    protected $primaryKey = 'category_id';
    public    $timestamps = false;

    public function description(): HasOne
    {
        return $this->hasOne(CategoryDescription::class, 'category_id', 'category_id')
            ->where('language_id', 2);
    }
}
