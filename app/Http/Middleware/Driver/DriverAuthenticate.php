<?php

namespace App\Http\Middleware\Driver;

use App\Services\Driver\DriverAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DriverAuthenticate
{
    protected DriverAuthService $authService;

    public function __construct(DriverAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'success' => false,
                'statusCode' => 401,
                'error' => 'UNAUTHORIZED',
                'message' => 'Unauthenticated: Bearer token is required.',
            ], 401);
        }

        $token = substr($authHeader, 7);
        $driver = $this->authService->validateAccessToken($token);

        if (!$driver) {
            return response()->json([
                'success' => false,
                'statusCode' => 401,
                'error' => 'UNAUTHORIZED',
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        if ($driver->status === 'blocked') {
            return response()->json([
                'success' => false,
                'statusCode' => 403,
                'error' => 'FORBIDDEN',
                'message' => 'Your driver account has been suspended or blocked.',
            ], 403);
        }

        // Attach driver to request attributes
        $request->attributes->set('driver', $driver);
        $request->setUserResolver(fn () => $driver->user);

        return $next($request);
    }
}
