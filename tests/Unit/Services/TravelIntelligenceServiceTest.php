<?php

declare(strict_types=1);

use App\Services\TravelIntelligenceService;
use Illuminate\Support\Facades\Log;

it('can be instantiated from the service container', function () {
    $service = app(TravelIntelligenceService::class);

    expect($service)->toBeInstanceOf(TravelIntelligenceService::class);
});

it('has the analyzeDestination method', function () {
    $service = app(TravelIntelligenceService::class);

    expect(method_exists($service, 'analyzeDestination'))->toBeTrue();
});

it('returns fallback response when service fails', function () {
    // Mock the Log facade to prevent facade root issues
    Log::spy();

    // Test the fallback mechanism by providing invalid coordinates
    // that will likely cause service failures
    $service = app(TravelIntelligenceService::class);

    // This should trigger the fallback response due to potential service failures
    $result = $service->analyzeDestination(999.0, 999.0);

    expect($result)->toBeArray();
    expect($result)->toHaveKeys([
        'position_info',
        'surface_indices',
        'weather_snapshot',
        'destination_health_score',
        'route_safety_rating',
        'timestamp',
        'valid_until',
    ]);

    // Should have fallback values
    expect($result['position_info']['latitude'])->toBe(999.0);
    expect($result['position_info']['longitude'])->toBe(999.0);
});
