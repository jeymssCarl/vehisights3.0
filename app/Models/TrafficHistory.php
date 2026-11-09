<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrafficHistory extends Model
{
    use HasFactory;

    protected $table = 'traffic_history';

    protected $fillable = [
        'session_id',
        'auto_session_id',
        'camera_id',
        'road_name',
        'direction',
        'hour_timestamp',
        'total_pcu',
        'vc_ratio',
        'los',
        'counts',
        'source',
    ];

    protected $casts = [
        'hour_timestamp' => 'datetime',
        'total_pcu' => 'decimal:2',
        'vc_ratio' => 'decimal:2',
        'counts' => 'array',
    ];

    public function camera()
    {
        return $this->belongsTo(Camera::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function autoSession()
    {
        return $this->belongsTo(AutoSession::class);
    }
}
