<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\CopernicusService;

final class AnalyzeSurfaceIndicesAction
{
    public function __construct(protected CopernicusService $copernicus) {}

    public function execute(float $lat, float $lng, string $mode = 'all', float $bboxSize = 0.01): array
    {
        // Determine bounding box
        $bbox = [
            $lng - $bboxSize,
            $lat + $bboxSize,
            $lng + $bboxSize,
            $lat - $bboxSize,
        ];

        // Build payload (depending on mode)
        // call copernicus service to process image
        $imageUrl = $this->copernicus->processImageWithBbox($bbox, $mode);

        // Compute summary metrics (stub for now)
        $metrics = [
            'vegetation_index' => 0.75,
            'water_index' => 0.10,
            'snow_index' => 0.05,
        ];

        return [
            'image_url' => $imageUrl,
            'bbox' => $bbox,
            'metrics' => $metrics,
        ];
    }
}
