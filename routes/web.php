<?php

declare(strict_types=1);

use App\Http\Controllers\CopernicusController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\GalileoController;
use App\Http\Controllers\RouteSafetyController;
use App\Http\Controllers\SatelliteController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\HotelController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WeatherController::class, 'index']);
Route::get('/copernicus/analyze', [CopernicusController::class, 'analyze']);
Route::get('/satellite/analyze', [SatelliteController::class, 'analyze'])->name('satellite.analyze');
Route::get('/destination/health', [DestinationController::class, 'health'])->name('destination.health');
Route::get('/galileo', [GalileoController::class, 'index'])->name('galileo.index');

// Route Planning and Safety Analysis
Route::get('/route/analyze', [RouteSafetyController::class, 'analyze'])->name('route.analyze');
Route::get('/route/quick-check', [RouteSafetyController::class, 'quickCheck'])->name('route.quick-check');

// Emergency Satellite Communication
Route::post('/emergency/activate', [EmergencyController::class, 'activateBeacon'])->name('emergency.activate');
Route::get('/emergency/status', [EmergencyController::class, 'getEmergencyStatus'])->name('emergency.status');
Route::post('/emergency/test', [EmergencyController::class, 'testEmergencySignal'])->name('emergency.test');
Route::delete('/emergency/cancel', [EmergencyController::class, 'cancelEmergency'])->name('emergency.cancel');
Route::get('/emergency/types', [EmergencyController::class, 'getEmergencyTypes'])->name('emergency.types');


Route::get('/hotels/nearby', [HotelController::class, 'nearby'])->name('hotels.nearby');
Route::post('/hotels/book', [HotelController::class, 'book'])->name('hotels.book');



Route::get('/hotels/test', function(App\Services\AmadeusHotelService $svc) {
  $lat = 42.380431163878484;
    $lng = 20.428198694885797;
    $checkIn  = now()->addDays(1)->format('Y-m-d');
    $checkOut = now()->addDays(2)->format('Y-m-d');
    $hotels = $svc->searchHotels($lat, $lng, $checkIn, $checkOut, 1);

    return response()->json([
        'input'  => compact('lat','lng','checkIn','checkOut'),
        'response' => $hotels
    ]);
});
