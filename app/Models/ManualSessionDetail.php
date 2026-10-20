<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManualSessionDetail extends Model
{
    use HasFactory;

    protected $table = 'manual_session_details';

    protected $fillable = [
        'manual_session_id',
        'total_pcu',
        'vc_ratio',
        'los',
        'counts',
        'pcu_totals',
        'source',
    ];

    protected $casts = [
        'counts' => 'array',
        'pcu_totals' => 'array',
        'total_pcu' => 'decimal:2',
        'vc_ratio' => 'decimal:2',
    ];

    public function manualSession()
    {
        return $this->belongsTo(ManualSession::class, 'manual_session_id');
    }
}