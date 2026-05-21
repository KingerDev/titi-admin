<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tester extends Model
{
    protected $connection = 'titi';
    protected $table = 'titi_testers';
    public $timestamps = false;

    protected $fillable = ['customer_id', 'note'];
}
