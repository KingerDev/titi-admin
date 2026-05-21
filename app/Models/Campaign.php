<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $connection = 'titi';
    protected $table = 'titi_notifications';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'name',
        'title',
        'image',
        'short_description',
        'long_description',
        'action_url',
        'is_active',
        'is_testing',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
        'is_active'  => 'boolean',
        'is_testing' => 'boolean',
    ];

    public function pushes(): HasMany
    {
        return $this->hasMany(CampaignPush::class, 'notification_id')->orderBy('created_at', 'desc');
    }

    public function scopeDraft($query)  { return $query->where('status', 'draft'); }
    public function scopeActive($query) { return $query->where('status', 'active'); }
}
