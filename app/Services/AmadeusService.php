<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AmadeusService
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $this->baseUrl      = config('services.amadeus.base_url');
        $this->clientId     = config('services.amadeus.client_id');
        $this->clientSecret = config('services.amadeus.client_secret');
    }

    /**
     * Get access token for Amadeus API (cached).
     */
    protected function getAccessToken(): string
    {
        return Cache::remember('amadeus_access_token', 3600, function () {
            $response = Http::asForm()->post("{$this->baseUrl}/v1/security/oauth2/token", [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
            ])->json();

            return $response['access_token'] ?? '';
        });
    }

    /**
     * Search hotels near a location using hotel list + hotel offers flow.
     * Returns hotel offers.
     */
    public function searchHotels(
        float  $lat,
        float  $lng,
        string $checkIn,
        string $checkOut,
        int    $adults = 1,
        int    $radiusKm = 5
    ): array {
        $token = $this->getAccessToken();

        // Step 1: Get hotelIds by geocode
        $listResponse = Http::withToken($token)
            ->get("{$this->baseUrl}/v1/reference-data/locations/hotels/by-geocode", [
                'latitude'  => $lat,
                'longitude' => $lng,
                'radius'    => $radiusKm,
                'radiusUnit'=> 'KM',
                'adults'    => $adults,
            ])->json();

        $hotelIds = collect(data_get($listResponse, 'data', []))
                    ->pluck('hotelId')
                    ->filter()
                    ->take(5)   // limit to first few
                    ->join(',');

        if (empty($hotelIds)) {
            return [];
        }

        // Step 2: Get hotel offers by hotelIds
        $offersResponse = Http::withToken($token)
            ->get("{$this->baseUrl}/v3/shopping/hotel-offers", [
                'hotelIds'     => $hotelIds,
                'adults'       => $adults,
                'checkInDate'  => $checkIn,
                'checkOutDate' => $checkOut,
            ])->json();

        return data_get($offersResponse, 'data', []);
    }

    /**
     * Search transfer offers (transport) for a given route or location.
     */
    public function searchTransfers(
        float  $fromLat,
        float  $fromLng,
        float  $toLat,
        float  $toLng,
        int    $passengers = 1
    ): array {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/v1/shopping/transfer-offers", [
                'origin'      => ['lat' => $fromLat, 'lng' => $fromLng],
                'destination' => ['lat' => $toLat,   'lng' => $toLng],
                'passengers'  => [ ['type'=>'ADULT','count'=>$passengers] ],
                'vehicle'     => [ 'passengerCount' => $passengers ],
            ])->json();

        return data_get($response, 'data', []);
    }
}
