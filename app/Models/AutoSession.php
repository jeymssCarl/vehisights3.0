<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoSession extends Model
{
    use HasFactory;

    protected $table = 'auto_sessions';

    protected $fillable = [
        'session_code',
        'camera_name',
        'ip_address',
        'location',
        'start_time',
        'end_time',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function detail()
    {
        return $this->hasOne(AutoSessionDetail::class, 'auto_session_id');
    }
}