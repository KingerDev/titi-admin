<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryDescription extends Model
{
    protected $connection = 'titi';
    protected $table      = 'titi_category_description';
    protected $primaryKey = 'category_id';
    public    $timestamps = false;
}
