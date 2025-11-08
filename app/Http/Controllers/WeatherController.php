<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\WeatherService;

final class WeatherController extends Controller
{
    public function index(WeatherService $service)
    {
        $lat = (float) request('lat', 42.6585);
        $lng = (float) request('lng', 21.1523);

        return response()->json($service->getWeatherData($lat, $lng));
    }
}
