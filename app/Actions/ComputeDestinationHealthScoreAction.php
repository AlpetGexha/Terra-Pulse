<?php

declare(strict_types=1);

// in app/Actions/ComputeDestinationHealthScoreAction.php

namespace App\Actions;

use App\Services\CopernicusService;
use App\Services\ScoringService;

final class ComputeDestinationHealthScoreAction
{
    protected ScoringService $scoring;

    protected CopernicusService $copernicus;

    public function __construct(ScoringService $scoring, CopernicusService $copernicus)
    {
        $this->scoring = $scoring;
        $this->copernicus = $copernicus;
    }

    public function execute(float $lat, float $lng): array
    {
        // Step 1: get surface metrics
        $surfaceResult = (new AnalyzeSurfaceIndicesAction($this->copernicus))
            ->execute($lat, $lng, 'all');

        $metrics = [
            'vegetation_index' => round(mt_rand(30, 90) / 100, 2),  // 0.30–0.90
            'water_index' => round(mt_rand(5, 30) / 100, 2),   // 0.05–0.30 (lower means water risk)
            'snow_index' => round(mt_rand(2, 20) / 100, 2),   // 0.02–0.20 (snow risk)
        ];
        $imageUrl = $surfaceResult['image_url'];
        $bbox = $surfaceResult['bbox'];

        // Step 2: use scoring
        $scoreResult = $this->scoring->computeDestinationHealth($lat, $lng, $metrics);

        // Step 3: build result
        return [
            'lat' => $lat,
            'lng' => $lng,
            'score' => $scoreResult['score'],
            'components' => $scoreResult['components'],
            'image_url' => $imageUrl,
            'valid_until' => now()->addHours(48)->toIso8601String(),
            'bbox' => $bbox,
        ];
    }
}
