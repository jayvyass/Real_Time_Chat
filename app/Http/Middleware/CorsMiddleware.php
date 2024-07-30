<?php

// app/Http/Middleware/CorsMiddleware.php
namespace App\Http\Middleware;

use Closure;

class CorsMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, X-Auth-Token, Authorization');

        if ($request->getMethod() === 'OPTIONS') {
            return response('', 200, $response->headers->all());
        }

        return $response;
    }
}
