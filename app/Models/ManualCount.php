<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManualCount extends Model
{
    use HasFactory;

    protected $table = 'manual_counts';

    protected $fillable = [
        'road_name',
        'barangay',
        'city',
        'direction_from',
        'direction_to',
        'count_date',
        'hour_start',
        'hour_end',
        'has_median',
        'lanes',
        'counts',
        'pcu_totals',
        'total_pcu',
        'vc_ratio',
        'los',
        'created_by',
    ];

    protected $casts = [
        'has_median' => 'boolean',
        'count_date' => 'date',
        'hour_start' => 'datetime',
        'hour_end' => 'datetime',
        'counts' => 'array',
        'pcu_totals' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}