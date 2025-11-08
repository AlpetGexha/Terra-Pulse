<?php

declare(strict_types=1);

namespace App\Services;

final class ScoringService
{
    /**
     * Compute destination health score from metrics and weather.
     *
     * @param float $lat
     * @param float $lng
     * @param array $metrics   // ['vegetation_index','water_index','snow_index']
     * @param array $weather   // ['precip_mm','visibility_km','air_quality_pm2_5','uv','is_day',…]
     * @return array
     */
    public function computeDestinationHealth(
        float $lat,
        float $lng,
        array $metrics,
        array $weather
    ): array {
        // Base weights
        $weights = [
            'vegetation_index'    => 0.50,
            'water_index'         => -0.30,
            'snow_index'          => -0.20,
            'precip_mm'           => -0.25,
            'visibility_km'       => 0.15,   // positive if visibility good, so weight opposite sign
            'air_quality_pm2_5'   => -0.10,
            'uv_extreme'          => -0.10,
            'night_travel'        => -0.10,
        ];

        // Extract metric values
        $veg   = $metrics['vegetation_index'] ?? 0;
        $water = $metrics['water_index']      ?? 0;
        $snow  = $metrics['snow_index']       ?? 0;

        // Extract weather values
        $precip       = $weather['precip_mm']               ?? 0;
        $visibility   = $weather['visibility_km']          ?? 10; // default good visibility
        $pm2_5        = $weather['air_quality_pm2_5']       ?? 0;
        $uv           = $weather['uv']                      ?? 0;
        $isDay        = $weather['is_day']                  ?? 1;  // 1=day, 0=night

        // Derived flags
        $uvExtreme    = $uv > 8 ? 1 : 0;
        $nightTravel  = $isDay === 0 ? 1 : 0;

        // Compute raw score
        $rawScore =
            ($veg  * $weights['vegetation_index']) +
            ($water * $weights['water_index']) +
            ($snow  * $weights['snow_index']) +
            ($precip  * $weights['precip_mm']) +
            (($visibility/10) * $weights['visibility_km']) +
            ($pm2_5 * $weights['air_quality_pm2_5']) +
            ($uvExtreme * $weights['uv_extreme']) +
            ($nightTravel * $weights['night_travel']);

        // Normalize from rawScore (range may vary) to 0-100
        $score = max(0, min(100, round(($rawScore + 1) * 50, 2)));

        // Breakdown contributions
        $components = [
            'vegetation_contribution' => round($veg * $weights['vegetation_index'] * 100, 2),
            'water_contribution'      => round($water * $weights['water_index'] * 100, 2),
            'snow_contribution'       => round($snow * $weights['snow_index'] * 100, 2),
            'precip_contribution'     => round($precip * $weights['precip_mm'] * 100, 2),
            'visibility_contribution' => round(($visibility/10) * $weights['visibility_km'] * 100, 2),
            'air_quality_contribution'=> round($pm2_5 * $weights['air_quality_pm2_5'] * 100, 2),
            'uv_extreme_contribution' => round($uvExtreme * $weights['uv_extreme'] * 100, 2),
            'night_travel_contribution'=> round($nightTravel * $weights['night_travel'] * 100, 2),
        ];

        return [
            'score'      => $score,
            'components' => $components,
        ];
    }
}
