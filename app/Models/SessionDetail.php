<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionDetail extends Model
{
    use HasFactory;

    protected $table = 'session_details';

    protected $fillable = [
        'session_id',
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

    public function session()
    {
        return $this->belongsTo(Session::class);
    }
}