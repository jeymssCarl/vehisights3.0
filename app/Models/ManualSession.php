<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManualSession extends Model
{
    use HasFactory;

    protected $table = 'manual_sessions';

    protected $fillable = [
        'session_code',
        'road_name',
        'barangay',
        'city',
        'direction_from',
        'direction_to',
        'lanes',
        'has_median',
        'base_capacity_per_lane',
        'start_time',
        'end_time',
        'status',
        'created_by',
    ];

    protected $casts = [
        'has_median' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function detail()
    {
        return $this->hasOne(ManualSessionDetail::class, 'manual_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}