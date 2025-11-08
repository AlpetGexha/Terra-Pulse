<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

final class WeatherService
{
    public function getWeatherData(float $lat, float $lng): array
    {
        $response = Http::get('https://api.weatherapi.com/v1/current.json', [
            'key' => config('services.weatherapi.key'),
            'q'   => "{$lat},{$lng}",
            'aqi' => 'yes',             // include air quality if available
        ])->json();

        return [
            'location'     => [
                'name'    => data_get($response, 'location.name'),
                'region'  => data_get($response, 'location.region'),
                'country' => data_get($response, 'location.country'),
                'lat'     => data_get($response, 'location.lat'),
                'lon'     => data_get($response, 'location.lon'),
                'localtime' => data_get($response, 'location.localtime'),
            ],
            'temperature'  => data_get($response, 'current.temp_c'),
            'condition'    => data_get($response, 'current.condition.text'),
            'uv'           => data_get($response, 'current.uv'),
            'precip_mm'    => data_get($response, 'current.precip_mm'),
            'humidity'     => data_get($response, 'current.humidity'),
            'cloud_cover'  => data_get($response, 'current.cloud'),
            'visibility_km' => data_get($response, 'current.vis_km'),
            'wind_kph'     => data_get($response, 'current.wind_kph'),
            'wind_dir'     => data_get($response, 'current.wind_dir'),
            'air_quality'  => [
                'pm2_5' => data_get($response, 'current.air_quality.pm2_5'),
                'pm10'  => data_get($response, 'current.air_quality.pm10'),
            ],
            'last_updated' => data_get($response, 'current.last_updated'),
        ];
    }
    public function getWeatherDataFromMeteor(float $lat, float $lng): array
    {
        $weather = Http::get('https://api.open-meteo.com/v1/forecast', [
            'latitude' => $lat,
            'longitude' => $lng,
            'current' => 'temperature_2m,uv_index,precipitation,cloudcover',
        ])->json();

        $air = Http::get('https://api.openaq.org/v2/latest', [
            'coordinates' => "{$lat},{$lng}",
        ])->json();

        return [
            'temperature' => $weather['current']['temperature_2m'] ?? null,
            'uv' => $weather['current']['uv_index'] ?? null,
            'air_quality' => $air['results'][0]['measurements'][0]['value'] ?? null,
        ];
    }
}
