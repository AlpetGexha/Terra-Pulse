<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AmadeusHotelService;

class HotelController extends Controller
{
    protected AmadeusHotelService $amadeus;

    public function __construct(AmadeusHotelService $amadeus)
    {
        $this->amadeus = $amadeus;
    }

    /**
     * Search hotels near given lat/lng + dates.
     */
    public function nearby(Request $request)
    {
        $validated = $request->validate([
            'lat'       => 'required|numeric',
            'lng'       => 'required|numeric',
            'checkIn'   => 'required|date',
            'checkOut'  => 'required|date|after:checkIn',
            'adults'    => 'nullable|integer|min:1',
        ]);

        $lat       = $validated['lat'];
        $lng       = $validated['lng'];
        $checkIn   = $validated['checkIn'];
        $checkOut  = $validated['checkOut'];
        $adults    = $validated['adults'] ?? 1;

        $hotels = $this->amadeus->searchHotels($lat, $lng, $checkIn, $checkOut, $adults);

        return response()->json([
            'status' => 'success',
            'data'   => $hotels,
        ]);
    }

    /**
     * Book a selected hotel offer.
     */
    public function book(Request $request)
    {
        $validated = $request->validate([
            'offerId'     => 'required|string',
            'guestInfo'   => 'required|array',
            'paymentInfo' => 'required|array',
        ]);

        $result = $this->amadeus->bookHotelOffer(
            $validated['offerId'],
            $validated['guestInfo'],
            $validated['paymentInfo']
        );

        return response()->json([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
