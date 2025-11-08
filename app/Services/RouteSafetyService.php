<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

final class RouteSafetyService
{
    public function __construct(
        protected GalileoService $galileo,
        protected CopernicusService $copernicus
    ) {}

    /**
     * Analyze route safety based on positioning and earth observation data.
     */
    public function analyzeRouteSafety(float $lat, float $lng, array $surfaceMetrics = []): string
    {
        try {
            // Get positioning accuracy from Galileo
            $positioningData = $this->galileo->getPositioningAccuracy($lat, $lng);
            $accuracy = $positioningData['accuracy'] ?? 10.0; // Default 10m accuracy

            // Analyze surface conditions from Copernicus data
            $vegetationIndex = $surfaceMetrics['vegetation_index'] ?? 0.5;
            $waterIndex = $surfaceMetrics['water_index'] ?? 0.1;
            $snowIndex = $surfaceMetrics['snow_index'] ?? 0.05;

            // Calculate safety score based on multiple factors
            $safetyScore = $this->calculateSafetyScore($accuracy, $vegetationIndex, $waterIndex, $snowIndex);

            return $this->getSafetyRating($safetyScore);
        } catch (Exception $e) {
            Log::error('Route safety analysis failed', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage(),
            ]);

            return 'MEDIUM'; // Default to medium safety when analysis fails
        }
    }

    private function calculateSafetyScore(float $accuracy, float $vegetation, float $water, float $snow): float
    {
        // Base safety score starts at 100
        $score = 100.0;

        // Penalize poor GPS accuracy (higher accuracy value = worse)
        if ($accuracy > 5.0) {
            $score -= min(30, ($accuracy - 5) * 3); // Max 30 point penalty
        }

        // High water index indicates flooding/water hazards
        if ($water > 0.2) {
            $score -= ($water - 0.2) * 100; // Reduce safety for water hazards
        }

        // High snow index indicates potential winter hazards
        if ($snow > 0.1) {
            $score -= ($snow - 0.1) * 80; // Reduce safety for snow/ice
        }

        // Low vegetation might indicate barren/difficult terrain
        if ($vegetation < 0.3) {
            $score -= (0.3 - $vegetation) * 50;
        }

        return max(0, min(100, $score));
    }

    private function getSafetyRating(float $score): string
    {
        return match (true) {
            $score >= 70 => 'HIGH',
            $score >= 40 => 'MEDIUM',
            default => 'LOW'
        };
    }
}
