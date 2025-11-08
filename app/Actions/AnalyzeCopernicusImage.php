<?php

declare(strict_types=1);

namespace App\Actions;

final class AnalyzeCopernicusImage
{
    public static function getEvalScript(): string
    {
        return <<<'EVAL'
//VERSION=3
function setup() {
  return { input: ["B03", "B04", "B08", "B11"], output: { bands: 3 } };
}
function evaluatePixel(s) {
  let ndvi = (s.B08 - s.B04) / (s.B08 + s.B04);
  let ndwi = (s.B03 - s.B08) / (s.B03 + s.B08);
  let ndsi = (s.B03 - s.B11) / (s.B03 + s.B11);
  return [ndvi, ndwi, ndsi];
}
EVAL;
    }

    public static function buildPayload(float $lat, float $lng, float $bboxSize = 0.01): array
    {
        $bbox = [
            $lng - $bboxSize,
            $lat + $bboxSize,
            $lng + $bboxSize,
            $lat - $bboxSize,
        ];

        return [
            'input' => [
                'bounds' => ['bbox' => $bbox],
                'data' => [[
                    'type' => 'sentinel-2-l2a',
                    'dataFilter' => [
                        'timeRange' => [
                            'from' => now()->subDays(10)->toIso8601String(),
                            'to' => now()->toIso8601String(),
                        ],
                        'maxCloudCoverage' => 20,
                    ],
                ]],
            ],
            'output' => [
                'width' => 512,
                'height' => 512,
                'responses' => [[
                    'identifier' => 'default',
                    'format' => ['type' => 'image/png'],
                ]],
            ],
            'evalscript' => self::getEvalScript(),
        ];
    }
}
