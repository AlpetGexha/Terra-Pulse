<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GalileoService;

final class GalileoController extends Controller
{
    public function index(GalileoService $service)
    {
        $path = storage_path('app/public/igs20_2388.atx');
        $data = $service->parseAntennaFile($path);

        return response()->json($data);
    }
}
