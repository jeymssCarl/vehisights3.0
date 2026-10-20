<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'auto_session_id',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function autoSession()
    {
        return $this->belongsTo(AutoSession::class);
    }
}