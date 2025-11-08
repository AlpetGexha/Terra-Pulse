<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AnalyzeCopernicusImage;
use App\Services\CopernicusService;

final class CopernicusController extends Controller
{
    public function analyze(CopernicusService $service)
    {
        // 42.40503460923687, 19.815049215865674
        $lat = (float) request('lat', 42.65);
        $lng = (float) request('lng', 21.15);

        $token = $service->getAccessToken();
        if (! $token) {
            return response()->json(['error' => 'Token failed'], 500);
        }

        $payload = AnalyzeCopernicusImage::buildPayload($lat, $lng, 1.0);
        $url = $service->processImage($payload, $token);

        return $url
            ? response()->json([
                'lat' => $lat,
                'lng' => $lng,
                'image_url' => $url,
                'legend' => [
                    'red' => 'NDVI (Vegetation)',
                    'green' => 'NDWI (Water)',
                    'blue' => 'NDSI (Snow)',
                ],
            ])
            : response()->json(['error' => 'Processing failed'], 500);
    }
}
