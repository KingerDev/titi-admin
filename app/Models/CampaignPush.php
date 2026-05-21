<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignPush extends Model
{
    protected $connection = 'titi';
    protected $table = 'titi_campaign_pushes';
    public $timestamps = false;

    protected $fillable = [
        'notification_id',
        'onesignal_id',
        'target_type',
        'target_store_id',
        'target_tester_id',
        'condition',
        'send_at',
        'status',
    ];

    protected $casts = [
        'send_at'    => 'datetime',
        'created_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'notification_id');
    }
}
