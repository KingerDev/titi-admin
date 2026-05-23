<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StandalonePush extends Model
{
    protected $connection = 'titi';
    protected $table = 'titi_standalone_pushes';
    public $timestamps = false;

    protected $fillable = [
        'onesignal_id',
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
        'send_at',
        'status',
        'recipients',
        'delivered',
        'failed',
        'converted',
        'stats_synced_at',
    ];

    protected $casts = [
        'send_at'         => 'datetime',
        'created_at'      => 'datetime',
        'priority'        => 'integer',
        'ttl'             => 'integer',
        'target_filters'  => 'array',
        'stats_synced_at' => 'datetime',
    ];
}
