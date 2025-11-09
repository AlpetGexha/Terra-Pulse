<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\AnalyzeCopernicusImage;
use App\Actions\AnalyzeSurfaceIndicesAction;
use Exception;
use Illuminate\Support\Facades\Log;

final class TravelIntelligenceService
{
public function __construct(
        protected GalileoService $galileo,
        protected CopernicusService $copernicus,
        protected WeatherService $weather,
        protected ScoringService $scoring,
        protected RouteSafetyService $routeSafety,
        protected AmadeusHotelService $amadeusHotelService,
        protected AmadeusService $amadeusService,

    ) {}

    /**
     * Analyze destination with unified travel intelligence data.
     */
    public function analyzeDestination(float $lat, float $lng): array
    {
        try {
            Log::info('Starting travel intelligence analysis', ['lat' => $lat, 'lng' => $lng]);

            // Step 1: Get precise positioning from Galileo
            $positionInfo = $this->galileo->getPositioningAccuracy($lat, $lng);
            Log::info('Galileo positioning completed', ['accuracy' => $positionInfo['accuracy']]);

            // Step 2: Analyze surface indices from Copernicus
            $surfaceAction = new AnalyzeSurfaceIndicesAction($this->copernicus);
            $surfaceResult = $surfaceAction->execute($lat, $lng, 'all');

            $vegetationIndex = $surfaceResult['metrics']['vegetation_index'];
            $waterIndex = $surfaceResult['metrics']['water_index'];
            $snowIndex = $surfaceResult['metrics']['snow_index'];

            Log::info('Copernicus surface analysis completed', [
                'vegetation' => $vegetationIndex,
                'water' => $waterIndex,
                'snow' => $snowIndex,
            ]);

            // Step 3: Get weather data
            $weatherData = $this->weather->getWeatherData($lat, $lng);
            Log::info('Weather data retrieved', ['temperature' => $weatherData['temperature']]);

            // Step 4: Calculate destination health score
            $metrics = [
                'vegetation_index' => $vegetationIndex,
                'water_index' => $waterIndex,
                'snow_index' => $snowIndex,
            ];
            $healthScore = $this->scoring->computeDestinationHealth($lat, $lng, $metrics, $weatherData);
            Log::info('Health score calculated', ['score' => $healthScore['score']]);

            // Step 5: Analyze route safety
            $routeSafety = $this->routeSafety->analyzeRouteSafety($lat, $lng, $metrics);
            Log::info('Route safety analysis completed', ['safety' => $routeSafety]);

            $imageAnalizer = app(\App\Services\CopernicusService::class);

            $payload = AnalyzeCopernicusImage::buildPayload($lat, $lng, 1);
            $token = $imageAnalizer->getAccessToken();
            $url_analizer = $imageAnalizer->processImage($payload, $token);

            // Step 6: Generate safety recommendations
            $safetyRecommendations = $this->generateSafetyRecommendations(
                $healthScore,
                $routeSafety,
                $weatherData,
                $metrics
            );


            // $hotels = $this->amadeusHotelService->searchHotels(
            //     $lat,
            //     $lng,
            //     now()->addDays(1)->format('Y-m-d'),
            //     now()->addDays(2)->format('Y-m-d'),
            //     2
            // );

            $hotels = $this->amadeusService->searchHotels(
                $lat,
                $lng,
                now()->addDays(1)->format('Y-m-d'),
                now()->addDays(2)->format('Y-m-d'),
                2
            );


            // $transfers = $this->amadeusService->searchTransfers(
            //     $startLat,
            //     $startLng,
            //     $lat,
            //     $lng,
            //     $passengers
            // );


            // Step 7: Compile unified response
            return [
                'position_info' => [
                    'latitude' => $positionInfo['latitude'],
                    'longitude' => $positionInfo['longitude'],
                    'altitude' => $positionInfo['altitude'],
                    'accuracy' => $positionInfo['accuracy'],
                    'satellite_count' => $positionInfo['satellite_count'],
                ],
                'surface_indices' => [
                    'vegetation_index' => $vegetationIndex,
                    'water_index' => $waterIndex,
                    'snow_index' => $snowIndex,
                ],
                'weather_snapshot' => $weatherData,
                'destination_health_score' => $healthScore['score'],
                'health_components' => $healthScore['components'],
                'route_safety_rating' => $routeSafety,
                'safety_recommendations' => $safetyRecommendations,
                'image_url' => $surfaceResult['image_url'],
                'url_analizer' => $url_analizer,
                'timestamp' => now()->toIso8601String(),
                'valid_until' => now()->addHours(48)->toIso8601String(),
                'bbox' => $surfaceResult['bbox'],
                // 'hotels' => $hotels,
                // 'transfers' => $transfers,
            ];
        } catch (Exception $e) {
            Log::error('Travel intelligence analysis failed', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return fallback data when analysis fails
            return $this->getFallbackResponse($lat, $lng);
        }
    }

    /**
     * Provide fallback response when full analysis fails.
     */
    private function getFallbackResponse(float $lat, float $lng): array
    {
        return [
            'position_info' => [
                'latitude' => $lat,
                'longitude' => $lng,
                'altitude' => 0.0,
                'accuracy' => 10.0,
                'satellite_count' => 4,
            ],
            'surface_indices' => [
                'vegetation_index' => 0.5,
                'water_index' => 0.1,
                'snow_index' => 0.05,
            ],
            'weather_snapshot' => [
                'temperature' => null,
                'uv_index' => null,
                'precipitation' => null,
                'air_quality' => null,
            ],
            'destination_health_score' => 50,
            'health_components' => [
                'vegetation_contribution' => 25.0,
                'water_contribution' => -3.0,
                'snow_contribution' => -4.0,
            ],
            'route_safety_rating' => 'MEDIUM',
            'image_url' => null,
            'timestamp' => now()->toIso8601String(),
            'valid_until' => now()->addHours(48)->toIso8601String(),
            'safety_recommendations' => $this->getFallbackSafetyRecommendations(),
            'bbox' => [$lng - 0.01, $lat + 0.01, $lng + 0.01, $lat - 0.01],
            'error' => 'Partial data due to service unavailability',
        ];
    }

    /**
     * Generate safety recommendations based on destination analysis.
     */
    private function generateSafetyRecommendations(
        array $healthScore,
        string $routeSafety,
        array $weatherData,
        array $surfaceMetrics
    ): array {
        $recommendations = [];
        $riskLevel = 'LOW';

        // Analyze overall health score
        $overallScore = $healthScore['score'];
        if ($overallScore < 30) {
            $riskLevel = 'HIGH';
            $recommendations[] = 'CAUTION: Low destination health score - consider alternative location';
        } elseif ($overallScore < 60) {
            $riskLevel = 'MEDIUM';
            $recommendations[] = 'Exercise normal caution - monitor conditions';
        } else {
            $recommendations[] = 'Good conditions for travel and outdoor activities';
        }

        // Route safety recommendations
        switch ($routeSafety) {
            case 'LOW':
                $recommendations[] = 'HIGH RISK: Avoid travel if possible, use extreme caution';
                $recommendations[] = 'Travel only during daylight hours';
                $recommendations[] = 'Inform others of your exact route and expected return';
                $riskLevel = 'HIGH';
                break;
            case 'MEDIUM':
                $recommendations[] = 'Moderate risk - use standard safety precautions';
                $recommendations[] = 'Check weather conditions before departure';
                break;
            case 'HIGH':
                $recommendations[] = 'Safe conditions - enjoy your activities';
                break;
        }

        // Weather-based recommendations
        if (isset($weatherData['temperature']) && $weatherData['temperature'] !== null) {
            $temp = $weatherData['temperature'];
            if ($temp < 0) {
                $recommendations[] = 'COLD WARNING: Dress warmly, risk of hypothermia';
                $recommendations[] = 'Carry emergency supplies and warm clothing';
            } elseif ($temp > 35) {
                $recommendations[] = 'HEAT WARNING: Stay hydrated, avoid midday sun';
                $recommendations[] = 'Wear sun protection and light-colored clothing';
            }
        }

        if (isset($weatherData['uv']) && $weatherData['uv'] > 8) {
            $recommendations[] = 'EXTREME UV: Use SPF 30+ sunscreen, seek shade during peak hours';
        }

        if (isset($weatherData['precip_mm']) && $weatherData['precip_mm'] > 10) {
            $recommendations[] = 'RAIN EXPECTED: Carry waterproof gear, beware of slippery surfaces';
        }

        if (isset($weatherData['visibility_km']) && $weatherData['visibility_km'] < 1) {
            $recommendations[] = 'POOR VISIBILITY: Reduce speed, use navigation aids';
        }

        if (isset($weatherData['air_quality_pm2_5']) && $weatherData['air_quality_pm2_5'] > 50) {
            $recommendations[] = 'POOR AIR QUALITY: Limit outdoor activities, consider face mask';
        }

        // Surface condition recommendations
        $vegetationIndex = $surfaceMetrics['vegetation_index'] ?? 0.5;
        $waterIndex = $surfaceMetrics['water_index'] ?? 0.1;
        $snowIndex = $surfaceMetrics['snow_index'] ?? 0.05;

        if ($waterIndex > 0.3) {
            $recommendations[] = 'HIGH WATER PRESENCE: Risk of flooding, avoid low-lying areas';
            $recommendations[] = 'Waterproof equipment recommended';
        }

        if ($snowIndex > 0.2) {
            $recommendations[] = 'SNOW/ICE CONDITIONS: Winter gear required, traction devices advised';
            $recommendations[] = 'Check avalanche conditions in mountainous areas';
        }

        if ($vegetationIndex < 0.2) {
            $recommendations[] = 'SPARSE VEGETATION: Limited natural shelter, bring sun protection';
            $recommendations[] = 'Carry extra water - arid conditions possible';
        }

        // GPS accuracy recommendations
        $components = $healthScore['components'] ?? [];
        if (abs($components['visibility_contribution'] ?? 0) < 5) {
            $recommendations[] = 'LIMITED VISIBILITY: Use GPS navigation, carry backup navigation tools';
        }

        // General safety recommendations
        $recommendations[] = 'Carry emergency communication device';
        $recommendations[] = 'Check local weather forecast before departure';
        $recommendations[] = 'Inform someone of your travel plans';

        return [
            'risk_level' => $riskLevel,
            'recommendations' => array_unique($recommendations),
            'priority_alerts' => $this->extractPriorityAlerts($recommendations),
            'equipment_suggestions' => $this->generateEquipmentSuggestions($weatherData, $surfaceMetrics, $routeSafety),
            'best_travel_times' => $this->suggestBestTravelTimes($weatherData, $routeSafety)
        ];
    }

    /**
     * Extract high priority alerts from recommendations.
     */
    private function extractPriorityAlerts(array $recommendations): array
    {
        $alerts = [];
        $alertKeywords = ['WARNING', 'HIGH RISK', 'CAUTION', 'EXTREME', 'DANGER'];

        foreach ($recommendations as $recommendation) {
            foreach ($alertKeywords as $keyword) {
                if (str_contains(strtoupper($recommendation), $keyword)) {
                    $alerts[] = $recommendation;
                    break;
                }
            }
        }

        return array_unique($alerts);
    }

    /**
     * Generate equipment suggestions based on conditions.
     */
    private function generateEquipmentSuggestions(array $weatherData, array $surfaceMetrics, string $routeSafety): array
    {
        $equipment = ['GPS device or smartphone with offline maps', 'First aid kit'];

        // Weather-based equipment
        $temp = $weatherData['temperature'] ?? 20;
        if ($temp < 5) {
            $equipment[] = 'Warm clothing and emergency blanket';
            $equipment[] = 'Insulated water bottles';
        } elseif ($temp > 30) {
            $equipment[] = 'Sun hat and UV protection clothing';
            $equipment[] = 'Extra water (3L+ per person)';
        }

        if (($weatherData['precip_mm'] ?? 0) > 5) {
            $equipment[] = 'Waterproof jacket and pants';
            $equipment[] = 'Dry bags for electronics';
        }

        if (($weatherData['uv'] ?? 0) > 6) {
            $equipment[] = 'SPF 30+ sunscreen';
            $equipment[] = 'Sunglasses with UV protection';
        }

        // Surface-based equipment
        if (($surfaceMetrics['snow_index'] ?? 0) > 0.1) {
            $equipment[] = 'Winter boots with good traction';
            $equipment[] = 'Trekking poles or ice axe if mountaineering';
        }

        if (($surfaceMetrics['water_index'] ?? 0) > 0.2) {
            $equipment[] = 'Waterproof boots or gaiters';
            $equipment[] = 'Water purification tablets/filter';
        }

        // Safety-based equipment
        if ($routeSafety === 'LOW') {
            $equipment[] = 'Satellite communication device (emergency beacon)';
            $equipment[] = 'Whistle for emergency signaling';
            $equipment[] = 'Headlamp with extra batteries';
        }

        return $equipment;
    }

    /**
     * Suggest optimal travel times based on conditions.
     */
    private function suggestBestTravelTimes(array $weatherData, string $routeSafety): array
    {
        $suggestions = [];

        // Safety-based timing
        if ($routeSafety === 'LOW') {
            $suggestions[] = 'Travel only during daylight hours (sunrise to sunset)';
            $suggestions[] = 'Avoid travel during adverse weather conditions';
        } else {
            $suggestions[] = 'Early morning or late afternoon for best visibility';
        }

        // Weather-based timing
        $temp = $weatherData['temperature'] ?? 20;
        $uv = $weatherData['uv'] ?? 0;

        if ($temp > 30 || $uv > 8) {
            $suggestions[] = 'Avoid midday hours (11 AM - 3 PM) due to heat/UV';
            $suggestions[] = 'Best times: Early morning (6-9 AM) or evening (5-7 PM)';
        }

        if ($temp < 0) {
            $suggestions[] = 'Travel during warmest part of day (10 AM - 2 PM)';
        }

        if (($weatherData['precip_mm'] ?? 0) > 10) {
            $suggestions[] = 'Check hourly weather forecast to avoid heaviest precipitation';
        }

        if (($weatherData['visibility_km'] ?? 10) < 2) {
            $suggestions[] = 'Wait for improved visibility conditions before traveling';
        }

        return $suggestions;
    }

    /**
     * Get fallback safety recommendations when full analysis fails.
     */
    private function getFallbackSafetyRecommendations(): array
    {
        return [
            'risk_level' => 'UNKNOWN',
            'recommendations' => [
                'Exercise standard travel caution',
                'Check local weather and road conditions',
                'Carry emergency communication device',
                'Inform others of your travel plans',
                'Monitor conditions throughout your journey'
            ],
            'priority_alerts' => [
                'Analysis temporarily unavailable - use extra caution'
            ],
            'equipment_suggestions' => [
                'GPS device or smartphone with offline maps',
                'First aid kit',
                'Weather-appropriate clothing',
                'Emergency supplies'
            ],
            'best_travel_times' => [
                'Travel during daylight hours when possible',
                'Check weather forecast before departure'
            ]
        ];
    }
}
