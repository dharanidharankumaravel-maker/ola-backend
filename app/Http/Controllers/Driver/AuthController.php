<?php

namespace App\Http\Controllers\Driver;

use App\Services\Driver\DriverAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseDriverController
{
    protected DriverAuthService $authService;

    public function __construct(DriverAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * 1. Send OTP / Login
     * POST /auth/send-otp
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phoneNumber' => 'required_without:phone|string',
            'phone'       => 'nullable|string',
            'countryCode' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $phoneNumber = (string) ($request->input('phoneNumber') ?? $request->input('phone'));
        $countryCode = (string) ($request->input('countryCode') ?? '+91');

        $result = $this->authService->sendOtp($phoneNumber, $countryCode);

        return $this->successResponse(
            $result,
            'OTP sent successfully.',
            200
        );
    }

    /**
     * 2. Verify OTP & Authenticate
     * POST /auth/verify-otp
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phoneNumber'    => 'required_without:phone|string',
            'phone'          => 'nullable|string',
            'otp'            => 'required|string',
            'verificationId' => 'nullable|string',
            'countryCode'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $phoneNumber    = (string) ($request->input('phoneNumber') ?? $request->input('phone'));
        $otp            = (string) $request->input('otp');
        $verificationId = $request->input('verificationId');
        $countryCode    = (string) ($request->input('countryCode') ?? '+91');

        $authResult = $this->authService->verifyOtp($phoneNumber, $otp, $verificationId, $countryCode);

        if (!$authResult) {
            return $this->errorResponse(
                'Invalid or expired verification code.',
                'INVALID_OTP',
                400,
                [
                    [
                        'field' => 'otp',
                        'rule' => 'invalid',
                        'message' => 'The provided OTP is invalid or has expired.',
                    ],
                ]
            );
        }

        if (!empty($authResult['blocked'])) {
            return $this->errorResponse(
                'Your driver account has been suspended or blocked.',
                'ACCOUNT_BLOCKED',
                403
            );
        }

        return $this->successResponse(
            $authResult,
            'Operation completed successfully.',
            200
        );
    }

    /**
     * 3. Refresh Access Token
     * POST /auth/refresh-token
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'refreshToken' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $refreshToken = (string) $request->input('refreshToken');
        $tokens = $this->authService->refreshTokens($refreshToken);

        if (!$tokens) {
            return $this->errorResponse(
                'Invalid or expired refresh token.',
                'UNAUTHORIZED',
                401
            );
        }

        return $this->successResponse(
            $tokens,
            'Operation completed successfully.',
            200
        );
    }

    /**
     * 4. Driver Logout
     * POST /auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        $authHeader = $request->header('Authorization');
        $accessToken = ($authHeader && str_starts_with($authHeader, 'Bearer ')) ? substr($authHeader, 7) : null;

        $this->authService->logout($driver, $accessToken);

        return $this->successResponse(
            null,
            'Logged out successfully.',
            200
        );
    }
}
