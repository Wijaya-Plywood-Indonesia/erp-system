<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TkinterApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key   = $request->header('X-API-KEY');
        $valid = config('app.tkinter_api_key');

        if (!$key || !$valid || !hash_equals($valid, $key)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
