<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
$lat = 42.658542426696094;
$lng = 21.152390693794455;

    // app/Services/TerraPulseService.ph
    $weather = Http::get("https://api.open-meteo.com/v1/forecast", [
        'latitude' => $lat,
        'longitude' => $lng,
        'current' => 'temperature_2m,uv_index,precipitation,cloudcover'
    ])->json();

    $air = Http::get("https://api.openaq.org/v2/latest", [
        'coordinates' => "{$lat},{$lng}"
    ])->json();

    return [
        'temperature' => $weather['current']['temperature_2m'],
        'uv' => $weather['current']['uv_index'],
        'air_quality' => $air['results'][0]['measurements'][0]['value'] ?? null
    ];
});
