<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TravelIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DestinationController extends Controller
{
    public function __construct(
        protected TravelIntelligenceService $travelIntelligenceService
    ) {}

    public function health(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $result = $this->travelIntelligenceService->analyzeDestination(
            (float) $validated['lat'],
            (float) $validated['lng']
        );

        return response()->json($result);
    }
}
