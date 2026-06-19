<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemMetric extends Model
{
    protected $fillable = [
        'metric_name',
        'metric_value',
        'metric_type',
        'recorded_at',
    ];

    protected $casts = [
        'metric_value' => 'float',
        'recorded_at' => 'datetime',
    ];

    public static function recordApiCall(string $service, float $responseTime)
    {
        self::create([
            'metric_name' => "api_call_{$service}",
            'metric_value' => $responseTime,
            'metric_type' => 'api_performance',
            'recorded_at' => now(),
        ]);
    }

    public static function recordError(string $errorType, string $service)
    {
        self::create([
            'metric_name' => "error_{$errorType}_{$service}",
            'metric_value' => 1,
            'metric_type' => 'error',
            'recorded_at' => now(),
        ]);
    }
}
