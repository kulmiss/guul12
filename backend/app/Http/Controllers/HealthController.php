<?php

namespace App\Http\Controllers;

use App\Services\Ops\HealthChecker;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /** GET /health — for an outside uptime monitor. No details are made public. */
    public function __invoke(HealthChecker $health): JsonResponse
    {
        return $health->databaseOk()
            ? response()->json(['status' => 'ok'])
            : response()->json(['status' => 'down'], 503);
    }
}
