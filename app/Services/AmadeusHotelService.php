<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AmadeusHotelService
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
     * Search hotels near given coordinates, checkIn/out dates, number of adults.
     */
    public function searchHotels(
        float  $lat,
        float  $lng,
        string $checkIn,
        string $checkOut,
        int    $adults = 1
    ): array {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/v3/shopping/hotel-offers", [
                'latitude'     => $lat,
                'longitude'    => $lng,
                'checkInDate'  => $checkIn,
                'checkOutDate' => $checkOut,
                'adults'       => $adults,
            ])->json();

        return $response['data'] ?? [];
    }

    /**
     * Book a hotel offer by offerId (guest info + payment required).
     */
    public function bookHotelOffer(string $offerId, array $guestInfo, array $paymentInfo): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/v2/booking/hotel-orders", [
                'offerId'   => $offerId,
                'guests'    => $guestInfo,
                'payments'  => $paymentInfo,
            ])->json();

        return $response;
    }
}
