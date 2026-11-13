<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EspApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key   = $request->header('X-API-KEY');
        $valid = config('app.esp32.key');

        if (!$key || !$valid || !hash_equals($valid, $key)) {
            return response()->json([
                'message' => 'Unauthorized',
                'debug_header_diterima' => $key,
                'debug_config_terbaca'  => $valid
            ], 401);
        }

        return $next($request);
    }
}
