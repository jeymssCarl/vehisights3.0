<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoSession extends Model
{
    use HasFactory;

    protected $table = 'auto_sessions';

    protected $fillable = [
        'schedule_id',
        'camera_id',
        'session_code',
        'camera_name',
        'ip_address',
        'location',
        'road_name',
        'barangay',
        'city',
        'direction',
        'day_classification',
        'lanes',
        'has_median',
        'base_capacity_per_lane',
        'start_time',
        'end_time',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'has_median' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function detail()
    {
        return $this->hasOne(AutoSessionDetail::class, 'auto_session_id');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function camera()
    {
        return $this->belongsTo(Camera::class);
    }

    public function trafficHistory()
    {
        return $this->hasOne(TrafficHistory::class);
    }
}
