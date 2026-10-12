<?php

namespace App\Http\Middleware\Customer;

use App\Services\Customer\CustomerAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerAuthenticate
{
    protected CustomerAuthService $authService;

    public function __construct(CustomerAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'statusCode' => 401,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Authorization token is missing.',
                ],
            ], 401);
        }

        $customer = $this->authService->validateAccessToken($token);

        if (!$customer) {
            $driverAuth = app(\App\Services\Driver\DriverAuthService::class);
            $driver = $driverAuth->validateAccessToken($token);
            if ($driver) {
                if ($request->is('*/logout') || $request->is('logout')) {
                    $driverAuth->logout($driver, $token);
                    return response()->json([
                        'success' => true,
                        'statusCode' => 200,
                        'message' => 'Logged out successfully.',
                    ], 200);
                }

                $request->attributes->set('driver', $driver);
                $request->attributes->set('auth_type', 'driver');
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'statusCode' => 401,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Invalid or expired access token.',
                ],
            ], 401);
        }

        $request->attributes->set('customer', $customer);
        $request->attributes->set('accessToken', $token);

        if ($customer->user) {
            $request->setUserResolver(fn () => $customer->user);
        }

        return $next($request);
    }
}
