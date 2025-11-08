<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AnalyzeSurfaceIndicesAction;
use Illuminate\Http\Request;

final class SatelliteController extends Controller
{
    public function analyze(Request $request, AnalyzeSurfaceIndicesAction $action)
    {
        $lat = (float) $request->input('lat');
        $lng = (float) $request->input('lng');
        $mode = $request->input('mode', 'all');

        $result = $action->execute($lat, $lng, $mode);

        return response()->json($result);
    }
}
