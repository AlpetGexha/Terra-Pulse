<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

final class GalileoService
{
    public function parseAntennaFile(string $path): array
    {
        $content = file_get_contents($path);
        $antennas = preg_split('/END OF ANTENNA/', $content);
        $data = collect();

        foreach ($antennas as $block) {
            if (preg_match('/TYPE \/ SERIAL NO\s+(.*)/', $block, $match)) {
                $type = trim($match[1]);
            }
            if (preg_match('/DAZI\s+([\d.]+)/', $block, $match)) {
                $dazi = (float) $match[1];
            }
            if (preg_match('/ZEN1 \/ ZEN2 \/ DZEN\s+(.*)/', $block, $match)) {
                $zen = trim($match[1]);
            }

            if (! empty($type ?? null)) {
                $data->push([
                    'type' => $type,
                    'dazi' => $dazi ?? null,
                    'zen' => $zen ?? null,
                ]);
            }
        }

        return $data->toArray();
    }

    /**
     * Get precise positioning data including accuracy metrics.
     */
    public function getPositioningAccuracy(float $lat, float $lng): array
    {
        try {
            // Simulate Galileo positioning accuracy based on location
            // In a real implementation, this would connect to Galileo API/service
            $accuracy = $this->calculatePositioningAccuracy($lat, $lng);

            return [
                'latitude' => $lat,
                'longitude' => $lng,
                'altitude' => $this->estimateAltitude($lat, $lng),
                'accuracy' => $accuracy, // meters
                'timestamp' => now()->toIso8601String(),
                'satellite_count' => rand(8, 12), // Simulated satellite count
                'hdop' => round($accuracy / 2, 2), // Horizontal dilution of precision
            ];
        } catch (Exception $e) {
            Log::error('Galileo positioning failed', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage(),
            ]);

            // Return fallback positioning data
            return [
                'latitude' => $lat,
                'longitude' => $lng,
                'altitude' => 0.0,
                'accuracy' => 10.0, // Default 10m accuracy
                'timestamp' => now()->toIso8601String(),
                'satellite_count' => 4, // Minimum for positioning
                'hdop' => 5.0,76
            ];
        }
    }

    /**
     * Calculate positioning accuracy based on various factors.
     */
    private function calculatePositioningAccuracy(float $lat, float $lng): float
    {
        // Base accuracy for Galileo system (typically 1-3 meters)
        $baseAccuracy = 2.0;

        // Factors that affect accuracy:

        // 1. Latitude factor (accuracy decreases near poles)
        $latFactor = 1 + (abs($lat) / 90) * 0.5;

        // 2. Urban vs rural (simulated based on coordinate patterns)
        $urbanFactor = $this->isUrbanArea($lat, $lng) ? 1.5 : 1.0;

        // 3. Random atmospheric conditions
        $atmosphericFactor = 1 + (rand(0, 30) / 100); // 0-30% variation

        $accuracy = $baseAccuracy * $latFactor * $urbanFactor * $atmosphericFactor;

        return round($accuracy, 2);
    }

    /**
     * Simple heuristic to determine if area is urban (affects GPS accuracy).
     */
    private function isUrbanArea(float $lat, float $lng): bool
    {
        // Simple heuristic based on coordinate density
        // In real implementation, this could use population density data
        $coordSum = abs($lat) + abs($lng);

        return $coordSum > 50 && $coordSum < 180;
    }

    /**
     * Estimate altitude for the given coordinates.
     */
    private function estimateAltitude(float $lat, float $lng): float
    {
        // Simplified altitude estimation
        // In real implementation, this would use elevation APIs

        // Mountain ranges (simplified)
        if (abs($lat) > 40 && abs($lng) > 80) {
            return rand(500, 3000); // Mountain elevation
        }

        // Coastal areas
        if (abs($lat) < 10 || abs($lng) < 10) {
            return rand(0, 100); // Near sea level
        }

        // Default elevation
        return rand(50, 500);
    }
}
