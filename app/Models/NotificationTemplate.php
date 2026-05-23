<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $connection = 'titi';
    protected $table = 'titi_notification_templates';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'title',
        'message',
        'subtitle',
        'image',
        'push_url',
        'target_type',
        'target_store_id',
        'target_tester_id',
        'target_segment',
        'target_filters',
        'ttl',
        'priority',
        'collapse_id',
        'ios_badge_type',
        'ios_badge_count',
    ];

    protected $casts = [
        'created_at'     => 'datetime',
        'target_filters' => 'array',
        'priority'       => 'integer',
        'ttl'            => 'integer',
    ];
}
