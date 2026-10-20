<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Camera extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ip_address',
        'location',
        'stream_url',
        'status',
        'last_detection_at',
        'detection_sensitivity',
        'roi',
        'created_by',
    ];

    protected $casts = [
        'roi' => 'array',
        'last_detection_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function autoSessions()
    {
        return $this->hasMany(AutoSession::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function predictions()
    {
        return $this->hasMany(Prediction::class);
    }

    public function trafficHistory()
    {
        return $this->hasMany(TrafficHistory::class);
    }
}