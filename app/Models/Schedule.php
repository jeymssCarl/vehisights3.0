<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_name',
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
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'recurrence_days' => 'array',
        'is_active' => 'boolean',
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
}