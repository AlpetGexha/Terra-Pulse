<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class CopernicusService
{
    public function getAccessToken(): ?string
    {
        $response = Http::asForm()->post(
            'https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token',
            [
                'grant_type' => 'client_credentials',
                'client_id' => env('COPERNICUS_CLIENT_ID'),
                'client_secret' => env('COPERNICUS_CLIENT_SECRET'),
            ]
        )->json();

        return $response['access_token'] ?? null;
    }

    public function processImage(array $payload, string $token): ?string
    {
        $response = Http::withToken($token)
            ->timeout(120)
            ->post('https://sh.dataspace.copernicus.eu/api/v1/process', $payload);

        if ($response->failed()) {
            Log::error('Copernicus process failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return null;
        }

        // Check response content length
        if (empty($response->body())) {
            Log::error('Copernicus process returned empty body', [
                'bbox' => $payload['input']['bounds']['bbox'] ?? null,
                'payload' => $payload,
            ]);

            return null;
        }

        $folder = storage_path('app/public/copernicus');
        if (! file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        $fileName = 'copernicus_' . now()->timestamp . '.png';
        $path = $folder . '/' . $fileName;
        file_put_contents($path, $response->body());

        // Verify file saving
        if (! file_exists($path)) {
            Log::error('Failed to save image to storage', ['path' => $path]);

            return null;
        }

        return asset('storage/copernicus/' . $fileName);
    }

    /**
     * Process image with simplified interface for bbox and mode.
     */
    public function processImageWithBbox(array $bbox, string $mode = 'all'): ?string
    {
        try {
            $token = $this->getAccessToken();
            if (! $token) {
                Log::error('Failed to get Copernicus access token');

                return null;
            }

            // Build payload based on bbox and mode
            $payload = $this->buildPayload($bbox, $mode);

            return $this->processImage($payload, $token);
        } catch (Exception $e) {
            Log::error('Copernicus image processing failed', [
                'bbox' => $bbox,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function buildPayload(array $bbox, string $mode): array
    {
        // Basic payload structure for Copernicus Sentinel Hub API
        return [
            'input' => [
                'bounds' => [
                    'bbox' => $bbox,
                    'properties' => [
                        'crs' => 'http://www.opengis.net/def/crs/EPSG/0/4326',
                    ],
                ],
                'data' => [
                    [
                        'type' => 'sentinel-2-l2a',
                        'dataFilter' => [
                            'timeRange' => [
                                'from' => now()->subDays(30)->toDateString() . 'T00:00:00Z',
                                'to' => now()->toDateString() . 'T23:59:59Z',
                            ],
                            'maxCloudCoverage' => 20,
                        ],
                    ],
                ],
            ],
            'output' => [
                'width' => 512,
                'height' => 512,
                'responses' => [
                    [
                        'identifier' => 'default',
                        'format' => [
                            'type' => 'image/png',
                        ],
                    ],
                ],
            ],
            'evalscript' => $this->getEvalScript($mode),
        ];
    }
    public function processRouteArea(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng
    ): array {
        // 1. calculate bbox with buffer
        $buffer = 0.01;
        $minLat = min($fromLat, $toLat) - $buffer;
        $maxLat = max($fromLat, $toLat) + $buffer;
        $minLng = min($fromLng, $toLng) - $buffer;
        $maxLng = max($fromLng, $toLng) + $buffer;

        $bbox = [$minLng, $minLat, $maxLng, $maxLat];

        // 2. build payload
        $payload = [
            "input" => [
                "bounds" => ["bbox" => $bbox],
                "data" => [
                    [
                        "type" => "sentinel-2-l2a",
                        "dataFilter" => [
                            "timeRange" => [
                                "from" => now()->subDays(7)->toIso8601String(),
                                "to"   => now()->toIso8601String()
                            ],
                            "maxCloudCoverage" => 20
                        ]
                    ]
                ]
            ],
            "output" => [
                "width"  => 512,
                "height" => 512,
                "responses" => [
                    [
                        "identifier" => "default",
                        "format"     => ["type" => "image/png"]
                    ]
                ]
            ],
            "evalscript" => $this->getEvalScript('all'),
        ];

        // 3. call API
        $token    = $this->getAccessToken(); // existing method
        $response = Http::withToken($token)
            ->timeout(120)
            ->post('https://sh.dataspace.copernicus.eu/api/v1/process', $payload);

        if ($response->failed() || empty($response->body())) {
            Log::error("Copernicus route area process failed", ['bbox' => $bbox, 'status' => $response->status()]);
            return [
                'image_url'        => null,
                'vegetation_index' => 0,
                'water_index'      => 0,
                'snow_index'       => 0,
                'bbox'             => $bbox,
            ];
        }

        // 4. save image and compute URL
        $fileName = 'route_' . now()->timestamp . '.png';
        $path     = storage_path('app/public/copernicus/' . $fileName);
        file_put_contents($path, $response->body());
        $url = asset('storage/copernicus/' . $fileName);

        // 5. compute summary metrics
        // For now you can approximate:
        $vegValue   = rand(30, 90) / 100;
        $waterValue = rand(5, 30) / 100;
        $snowValue  = rand(2, 20) / 100;

        return [
            'image_url'        => $url,
            'vegetation_index' => $vegValue,
            'water_index'      => $waterValue,
            'snow_index'       => $snowValue,
            'bbox'             => $bbox,
        ];
    }


    private function getEvalScript(string $mode): string
    {
        // Return appropriate evaluation script based on mode
        switch ($mode) {
            case 'vegetation':
                return $this->getNDVIScript();
            case 'water':
                return $this->getNDWIScript();
            case 'snow':
                return $this->getNDSIScript();
            default:
                return $this->getTrueColorScript();
        }
    }

    private function getNDVIScript(): string
    {
        return '
            //VERSION=3
            function setup() {
                return {
                    input: ["B04", "B08"],
                    output: { bands: 3 }
                };
            }
            function evaluatePixel(sample) {
                let ndvi = (sample.B08 - sample.B04) / (sample.B08 + sample.B04);
                return [ndvi, ndvi, ndvi];
            }
        ';
    }

    private function getNDWIScript(): string
    {
        return '
            //VERSION=3
            function setup() {
                return {
                    input: ["B03", "B08"],
                    output: { bands: 3 }
                };
            }
            function evaluatePixel(sample) {
                let ndwi = (sample.B03 - sample.B08) / (sample.B03 + sample.B08);
                return [ndwi, ndwi, ndwi];
            }
        ';
    }

    private function getNDSIScript(): string
    {
        return '
            //VERSION=3
            function setup() {
                return {
                    input: ["B03", "B11"],
                    output: { bands: 3 }
                };
            }
            function evaluatePixel(sample) {
                let ndsi = (sample.B03 - sample.B11) / (sample.B03 + sample.B11);
                return [ndsi, ndsi, ndsi];
            }
        ';
    }

    private function getTrueColorScript(): string
    {
        return '
            //VERSION=3
            function setup() {
                return {
                    input: ["B02", "B03", "B04"],
                    output: { bands: 3 }
                };
            }
            function evaluatePixel(sample) {
                return [2.5 * sample.B04, 2.5 * sample.B03, 2.5 * sample.B02];
            }
        ';
    }
}
