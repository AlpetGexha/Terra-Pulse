<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

final class RoutePlanningService
{
    public function __construct(
        protected TravelIntelligenceService $travelIntelligence,
        protected RouteSafetyService $routeSafety,
        protected WeatherService $weather,
        protected CopernicusService $copernicus
    ) {}

    /**
     * Analyze route feasibility and safety from origin to destination.
     * 
     * @param float $originLat Origin latitude
     * @param float $originLng Origin longitude  
     * @param float $destLat Destination latitude
     * @param float $destLng Destination longitude
     * @param string $travelMode Travel mode: 'driving', 'walking', 'cycling'
     * @return array Complete route analysis with safety recommendations
     */
    public function analyzeRoute(
        float $originLat,
        float $originLng, 
        float $destLat,
        float $destLng,
        string $travelMode = 'driving'
    ): array {
        try {
            Log::info('Starting route analysis', [
                'origin' => [$originLat, $originLng],
                'destination' => [$destLat, $destLng],
                'mode' => $travelMode
            ]);

            // Step 1: Analyze origin conditions
            $originAnalysis = $this->travelIntelligence->analyzeDestination($originLat, $originLng);
            
            // Step 2: Analyze destination conditions  
            $destAnalysis = $this->travelIntelligence->analyzeDestination($destLat, $destLng);
            
            // Step 3: Calculate route waypoints and analyze each segment
            $routeSegments = $this->generateRouteSegments($originLat, $originLng, $destLat, $destLng);
            
            // Step 4: Assess overall travel feasibility
            $feasibility = $this->assessTravelFeasibility($originAnalysis, $destAnalysis, $routeSegments, $travelMode);
            
            // Step 5: Generate safety recommendations
            $recommendations = $this->generateSafetyRecommendations($feasibility, $originAnalysis, $destAnalysis, $travelMode);
            
            // Step 6: Calculate best route options
            $routeOptions = $this->calculateRouteOptions($routeSegments, $feasibility, $travelMode);

            return [
                'travel_feasible' => $feasibility['is_feasible'],
                'overall_risk_level' => $feasibility['risk_level'],
                'risk_score' => $feasibility['risk_score'],
                'origin_conditions' => [
                    'safety_rating' => $originAnalysis['route_safety_rating'],
                    'health_score' => $originAnalysis['destination_health_score'],
                    'weather' => $originAnalysis['weather_snapshot']
                ],
                'destination_conditions' => [
                    'safety_rating' => $destAnalysis['route_safety_rating'], 
                    'health_score' => $destAnalysis['destination_health_score'],
                    'weather' => $destAnalysis['weather_snapshot']
                ],
                'route_segments' => $routeSegments,
                'recommended_route' => $routeOptions['best_route'],
                'alternative_routes' => $routeOptions['alternatives'],
                'safety_recommendations' => $recommendations,
                'travel_advisories' => $feasibility['advisories'],
                'estimated_travel_time' => $this->estimateTravelTime($routeSegments, $travelMode),
                'weather_forecast' => $this->getRouteWeatherForecast($routeSegments),
                'timestamp' => now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('Route analysis failed', [
                'error' => $e->getMessage(),
                'origin' => [$originLat, $originLng],
                'destination' => [$destLat, $destLng]
            ]);

            return $this->getFallbackRouteAnalysis($originLat, $originLng, $destLat, $destLng, $travelMode);
        }
    }

    /**
     * Generate intermediate waypoints for route analysis.
     */
    private function generateRouteSegments(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        $segments = [];
        
        // Calculate distance and determine number of checkpoints
        $distance = $this->calculateDistance($originLat, $originLng, $destLat, $destLng);
        $numSegments = min(10, max(3, (int) ceil($distance / 50))); // 1 segment per ~50km
        
        for ($i = 0; $i <= $numSegments; $i++) {
            $ratio = $i / $numSegments;
            $segmentLat = $originLat + ($destLat - $originLat) * $ratio;
            $segmentLng = $originLng + ($destLng - $originLng) * $ratio;
            
            // Analyze each segment
            try {
                $segmentAnalysis = $this->travelIntelligence->analyzeDestination($segmentLat, $segmentLng);
                $segments[] = [
                    'position' => ['lat' => $segmentLat, 'lng' => $segmentLng],
                    'safety_rating' => $segmentAnalysis['route_safety_rating'],
                    'health_score' => $segmentAnalysis['destination_health_score'],
                    'surface_conditions' => $segmentAnalysis['surface_indices'],
                    'segment_index' => $i,
                    'distance_from_origin' => $distance * $ratio
                ];
            } catch (Exception $e) {
                Log::warning('Segment analysis failed', ['segment' => $i, 'error' => $e->getMessage()]);
                $segments[] = [
                    'position' => ['lat' => $segmentLat, 'lng' => $segmentLng],
                    'safety_rating' => 'MEDIUM',
                    'health_score' => 50,
                    'surface_conditions' => ['error' => 'Analysis unavailable'],
                    'segment_index' => $i,
                    'distance_from_origin' => $distance * $ratio
                ];
            }
        }
        
        return $segments;
    }

    /**
     * Assess if travel is feasible and safe.
     */
    private function assessTravelFeasibility(array $origin, array $dest, array $segments, string $travelMode): array
    {
        $riskFactors = [];
        $totalRiskScore = 0;
        $advisories = [];

        // Check origin conditions
        if ($origin['route_safety_rating'] === 'LOW') {
            $riskFactors[] = 'High risk origin location';
            $totalRiskScore += 30;
        }
        if ($origin['destination_health_score'] < 30) {
            $riskFactors[] = 'Poor environmental conditions at origin';
            $totalRiskScore += 20;
        }

        // Check destination conditions
        if ($dest['route_safety_rating'] === 'LOW') {
            $riskFactors[] = 'High risk destination';
            $totalRiskScore += 30;
        }
        if ($dest['destination_health_score'] < 30) {
            $riskFactors[] = 'Poor environmental conditions at destination';
            $totalRiskScore += 20;
        }

        // Analyze route segments
        $lowSafetySegments = 0;
        foreach ($segments as $segment) {
            if ($segment['safety_rating'] === 'LOW') {
                $lowSafetySegments++;
                $totalRiskScore += 15;
            }
            if ($segment['health_score'] < 25) {
                $totalRiskScore += 10;
            }
        }

        if ($lowSafetySegments > 0) {
            $riskFactors[] = "{$lowSafetySegments} high-risk route segments detected";
        }

        // Travel mode specific checks
        if ($travelMode === 'walking' && $totalRiskScore > 50) {
            $advisories[] = 'Walking not recommended due to high environmental risks';
        }
        if ($travelMode === 'cycling' && $lowSafetySegments > count($segments) * 0.3) {
            $advisories[] = 'Cycling may be dangerous due to route conditions';
        }

        // Determine overall feasibility
        $riskLevel = match (true) {
            $totalRiskScore >= 80 => 'CRITICAL',
            $totalRiskScore >= 60 => 'HIGH', 
            $totalRiskScore >= 30 => 'MEDIUM',
            default => 'LOW'
        };

        $isFeasible = match ($riskLevel) {
            'CRITICAL' => false,
            'HIGH' => $travelMode === 'driving', // Only driving allowed in high risk
            default => true
        };

        if (!$isFeasible) {
            $advisories[] = 'Travel NOT RECOMMENDED due to critical safety risks';
        }

        return [
            'is_feasible' => $isFeasible,
            'risk_level' => $riskLevel,
            'risk_score' => $totalRiskScore,
            'risk_factors' => $riskFactors,
            'advisories' => $advisories
        ];
    }

    /**
     * Generate safety recommendations based on analysis.
     */
    private function generateSafetyRecommendations(array $feasibility, array $origin, array $dest, string $travelMode): array
    {
        $recommendations = [];

        if ($feasibility['risk_level'] === 'HIGH' || $feasibility['risk_level'] === 'CRITICAL') {
            $recommendations[] = 'Consider postponing travel until conditions improve';
            $recommendations[] = 'Monitor weather and environmental conditions closely';
        }

        if ($origin['route_safety_rating'] === 'LOW' || $dest['route_safety_rating'] === 'LOW') {
            $recommendations[] = 'Use GPS navigation with real-time traffic updates';
            $recommendations[] = 'Travel during daylight hours only';
        }

        if ($travelMode === 'walking' || $travelMode === 'cycling') {
            $recommendations[] = 'Bring emergency supplies and communication device';
            $recommendations[] = 'Inform others of your planned route and timeline';
        }

        $recommendations[] = 'Check for local travel advisories before departure';
        $recommendations[] = 'Have alternative routes planned in case of emergencies';

        return $recommendations;
    }

    /**
     * Calculate route options and recommend the best one.
     */
    private function calculateRouteOptions(array $segments, array $feasibility, string $travelMode): array
    {
        // For now, we provide a single optimal route
        // In a real implementation, this would use routing APIs like Google Maps, OpenRouteService, etc.
        
        $bestRoute = [
            'route_type' => 'optimal_safety',
            'description' => 'Route optimized for safety and environmental conditions',
            'waypoints' => array_map(fn($segment) => $segment['position'], $segments),
            'safety_score' => 100 - $feasibility['risk_score'],
            'recommended_mode' => $travelMode
        ];

        $alternatives = [];
        
        if ($feasibility['risk_level'] !== 'CRITICAL') {
            $alternatives[] = [
                'route_type' => 'fastest',
                'description' => 'Direct route with minimal stops',
                'safety_score' => max(0, 100 - $feasibility['risk_score'] - 20),
                'note' => 'Faster but potentially higher risk'
            ];
        }

        return [
            'best_route' => $bestRoute,
            'alternatives' => $alternatives
        ];
    }

    /**
     * Get weather forecast along the route.
     */
    private function getRouteWeatherForecast(array $segments): array
    {
        $forecast = [];
        
        // Sample a few key points along the route for weather
        $keyPoints = array_slice($segments, 0, min(5, count($segments)));
        
        foreach ($keyPoints as $point) {
            try {
                $weather = $this->weather->getWeatherData($point['position']['lat'], $point['position']['lng']);
                $forecast[] = [
                    'location' => $point['position'],
                    'weather' => $weather,
                    'segment_index' => $point['segment_index']
                ];
            } catch (Exception $e) {
                Log::warning('Weather forecast failed for route point', ['error' => $e->getMessage()]);
            }
        }
        
        return $forecast;
    }

    /**
     * Estimate travel time based on segments and mode.
     */
    private function estimateTravelTime(array $segments, string $travelMode): array
    {
        if (empty($segments)) {
            return ['hours' => 0, 'minutes' => 0];
        }

        $totalDistance = end($segments)['distance_from_origin'];
        
        // Average speeds by travel mode (km/h)
        $speeds = [
            'driving' => 60,
            'cycling' => 20,
            'walking' => 5
        ];
        
        $speed = $speeds[$travelMode] ?? 60;
        $hours = $totalDistance / $speed;
        
        return [
            'hours' => (int) floor($hours),
            'minutes' => (int) (($hours - floor($hours)) * 60),
            'total_distance_km' => round($totalDistance, 2)
        ];
    }

    /**
     * Calculate distance between two points in kilometers.
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    /**
     * Fallback response when analysis fails.
     */
    private function getFallbackRouteAnalysis(float $originLat, float $originLng, float $destLat, float $destLng, string $travelMode): array
    {
        return [
            'travel_feasible' => null,
            'overall_risk_level' => 'UNKNOWN',
            'risk_score' => null,
            'origin_conditions' => ['error' => 'Analysis unavailable'],
            'destination_conditions' => ['error' => 'Analysis unavailable'],
            'route_segments' => [],
            'recommended_route' => null,
            'alternative_routes' => [],
            'safety_recommendations' => [
                'Route analysis temporarily unavailable',
                'Use caution and check local conditions',
                'Consider using standard navigation services'
            ],
            'travel_advisories' => ['System temporarily unavailable'],
            'estimated_travel_time' => $this->estimateTravelTime([
                ['distance_from_origin' => $this->calculateDistance($originLat, $originLng, $destLat, $destLng)]
            ], $travelMode),
            'weather_forecast' => [],
            'timestamp' => now()->toISOString(),
            'error' => 'Partial data - analysis services unavailable'
        ];
    }
}
