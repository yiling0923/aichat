<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/** For an uptime monitor: 200 when the database works and the scheduler ran in the last 5 minutes, else 503. No secrets shown. */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];

        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (Throwable) {
            $checks['database'] = false;
        }

        $beat = Cache::get('scheduler-heartbeat');
        $checks['scheduler'] = $beat !== null && now()->diffInMinutes($beat, true) < 5;

        $ok = ! in_array(false, $checks, true);

        return response()->json(['ok' => $ok] + $checks, $ok ? 200 : 503);
    }
}
