<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'camera_id',
        'road_name',
        'direction',
        'prediction_type',
        'prediction_date',
        'prediction_hour',
        'predicted_pcu',
        'predicted_vc_ratio',
        'predicted_los',
        'confidence_score',
        'actual_pcu',
        'model_version',
    ];

    protected $casts = [
        'prediction_date' => 'date',
        'predicted_pcu' => 'decimal:2',
        'predicted_vc_ratio' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'actual_pcu' => 'decimal:2',
    ];

    public function camera()
    {
        return $this->belongsTo(Camera::class);
    }
}