<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoSessionDetail extends Model
{
    use HasFactory;

    protected $table = 'auto_session_details';

    protected $fillable = [
        'auto_session_id',
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
    ];

    public function autoSession()
    {
        return $this->belongsTo(AutoSession::class, 'auto_session_id');
    }
}