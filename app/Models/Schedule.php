<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_name',
        'road_name',
        'barangay',
        'city',
        'direction_from',
        'direction_to',
        'day_classification',
        'lanes',
        'has_median',
        'base_capacity_per_lane',
        'camera_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'recurrence',
        'recurrence_days',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
        'recurrence_days' => 'array',
        'is_active' => 'boolean',
        'has_median' => 'boolean',
    ];

    public function camera()
    {
        return $this->belongsTo(Camera::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(ScheduleLog::class);
    }

    public function autoSessions()
    {
        return $this->hasMany(AutoSession::class);
    }
}
