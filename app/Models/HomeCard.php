<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeCard extends Model
{
    protected $connection = 'titi';
    protected $table = 'titi_home_card';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'top_text',
        'title',
        'subtitle',
        'bg_color',
        'text_color',
        'top_text_color',
        'col_span',
        'row_span',
        'app_route',
        'external_url',
        'image_url',
        'pattern',
        'decor',
        'show_arrow',
        'audience',
        'platform',
        'loyalty_visibility',
        'sort_order',
        'active',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'col_span'     => 'integer',
        'row_span'     => 'integer',
        'sort_order'   => 'integer',
        'active'       => 'boolean',
        'show_arrow'   => 'boolean',
        'valid_from'   => 'datetime',
        'valid_to'     => 'datetime',
    ];
}
