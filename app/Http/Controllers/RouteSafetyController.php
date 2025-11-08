<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\RoutePlanningService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class RouteSafetyController extends Controller
{
    public function __construct(
        protected RoutePlanningService $routePlanning
    ) {}

    /**
     * Analyze route safety and feasibility between two points.
     */
    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin_lat' => 'required|numeric|between:-90,90',
            'origin_lng' => 'required|numeric|between:-180,180',
            'dest_lat' => 'required|numeric|between:-90,90', 
            'dest_lng' => 'required|numeric|between:-180,180',
            'travel_mode' => 'nullable|string|in:driving,walking,cycling'
        ]);

        $originLat = (float) $validated['origin_lat'];
        $originLng = (float) $validated['origin_lng'];
        $destLat = (float) $validated['dest_lat'];
        $destLng = (float) $validated['dest_lng'];
        $travelMode = $validated['travel_mode'] ?? 'driving';

        $result = $this->routePlanning->analyzeRoute(
            $originLat,
            $originLng, 
            $destLat,
            $destLng,
            $travelMode
        );

        return response()->json($result);
    }

    /**
     * Quick safety check for a single destination.
     */
    public function quickCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180'
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];

        // Use route planning to analyze just the destination
        $result = $this->routePlanning->analyzeRoute($lat, $lng, $lat, $lng, 'driving');
        
        return response()->json([
            'location' => ['lat' => $lat, 'lng' => $lng],
            'safety_rating' => $result['destination_conditions']['safety_rating'] ?? 'MEDIUM',
            'health_score' => $result['destination_conditions']['health_score'] ?? 50,
            'travel_feasible' => $result['travel_feasible'] ?? null,
            'risk_level' => $result['overall_risk_level'] ?? 'UNKNOWN',
            'recommendations' => $result['safety_recommendations'] ?? [],
            'timestamp' => $result['timestamp']
        ]);
    }
}
