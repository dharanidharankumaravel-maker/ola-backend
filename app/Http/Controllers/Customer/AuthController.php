<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Customer\CustomerAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected CustomerAuthService $authService;

    public function __construct(CustomerAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * 1.1 Send OTP
     * POST /auth/otp/send
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $phone  = $request->input('phone');
        $fcmKey = $request->input('fcm_key') ?? $request->input('fcmKey');

        // Validate phone: must be present and exactly 10 digits
        if (empty($phone) || !is_string($phone)) {
            return $this->errorResponse(
                'INVALID_PHONE_NUMBER',
                'The phone number is required.',
                400
            );
        }

        if (!$this->isValidPhoneNumber($phone)) {
            return $this->errorResponse(
                'INVALID_PHONE_NUMBER',
                'The phone number format provided is invalid.',
                400
            );
        }

        $phone  = trim($phone);
        $fcmKey = (is_string($fcmKey) && trim($fcmKey) !== '') ? trim($fcmKey) : null;
        $result = $this->authService->sendOtp($phone, $fcmKey);

        return $this->successResponse(
            [
                'phone'             => $result['phone'],
                'otp'               => $result['otp'],
                'expiresIn'         => $result['expiresIn'],
                'resendAvailableIn' => $result['resendAvailableIn'],
            ],
            'OTP sent successfully',
            200
        );
    }

    /**
     * 1.2 Verify OTP & Login
     * POST /auth/otp/verify
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $phone      = $request->input('phone');
        $otp        = $request->input('otp');
        $fcmKey     = $request->input('fcm_key') ?? $request->input('fcmKey');
        $deviceName = $request->input('device_name') ?? $request->input('deviceName');
        $deviceType = $request->input('device_type') ?? $request->input('deviceType');
        $deviceOs   = $request->input('device_os') ?? $request->input('deviceOs');

        if (!$this->isValidPhoneNumber($phone)) {
            return $this->errorResponse(
                'INVALID_PHONE_NUMBER',
                'The phone number format provided is invalid.',
                400
            );
        }

        if (empty($otp) || !is_string($otp)) {
            return $this->errorResponse(
                'INVALID_OTP',
                'The OTP is required.',
                400
            );
        }

        if (empty($fcmKey) || !is_string($fcmKey) || trim($fcmKey) === '') {
            return $this->errorResponse(
                'FCM_KEY_REQUIRED',
                'The FCM key is required.',
                400
            );
        }

        if (empty($deviceName) || !is_string($deviceName) || trim($deviceName) === '') {
            return $this->errorResponse(
                'DEVICE_NAME_REQUIRED',
                'The device name is required.',
                400
            );
        }

        if (empty($deviceType) || !is_string($deviceType) || trim($deviceType) === '') {
            return $this->errorResponse(
                'DEVICE_TYPE_REQUIRED',
                'The device type is required.',
                400
            );
        }

        if (empty($deviceOs) || !is_string($deviceOs) || trim($deviceOs) === '') {
            return $this->errorResponse(
                'DEVICE_OS_REQUIRED',
                'The device OS is required.',
                400
            );
        }

        $phone      = trim($phone);
        $otp        = trim($otp);
        $fcmKey     = trim($fcmKey);
        $deviceName = trim($deviceName);
        $deviceType = trim($deviceType);
        $deviceOs   = trim($deviceOs);

        $authData = $this->authService->verifyOtp(
            $phone,
            $otp,
            $fcmKey,
            $deviceName,
            $deviceType,
            $deviceOs
        );

        if ($authData === null) {
            return $this->errorResponse(
                'INVALID_OTP',
                'The OTP provided is invalid or has expired.',
                400
            );
        }

        if (isset($authData['blocked']) && $authData['blocked']) {
            return $this->errorResponse(
                'ACCOUNT_BLOCKED',
                'Your account has been suspended. Please contact customer support.',
                403
            );
        }

        return $this->successResponse(
            [
                'user' => $authData['user'],
                'tokens' => $authData['tokens'],
                'isNewUser' => $authData['isNewUser'],
            ],
            'Authentication successful',
            200
        );
    }

    /**
     * 1.3 Refresh Auth Token
     * POST /auth/refresh
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refreshToken');

        if (empty($refreshToken) || !is_string($refreshToken)) {
            return $this->errorResponse(
                'INVALID_REFRESH_TOKEN',
                'The refresh token is required.',
                400
            );
        }

        $newTokens = $this->authService->refreshTokens(trim($refreshToken));

        if (!$newTokens) {
            return $this->errorResponse(
                'INVALID_REFRESH_TOKEN',
                'The refresh token is invalid or has expired.',
                400
            );
        }

        return $this->successResponse(
            [
                'accessToken' => $newTokens['accessToken'],
                'refreshToken' => $newTokens['refreshToken'],
            ],
            null,
            200
        );
    }

    /**
     * 1.4 Get Current User Profile (getMe)
     * GET /auth/me
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = $request->attributes->get('customer');

        if (!$customer) {
            return $this->errorResponse(
                'UNAUTHORIZED',
                'Unauthorized. Customer session not found.',
                401
            );
        }

        // Refresh customer model from database to ensure fresh data
        $customer->refresh();

        return $this->successResponse(
            $this->authService->formatCustomerProfile($customer),
            null,
            200
        );
    }

    /**
     * 1.5 Logout
     * POST /auth/logout
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refreshToken');
        $accessToken = $request->bearerToken();
        $customer = $request->attributes->get('customer');

        $this->authService->logout($customer, $refreshToken, $accessToken);

        return $this->successResponse(
            null,
            'Logged out successfully',
            200
        );
    }

    /**
     * Standardized success response structure.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function successResponse($data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'statusCode' => $statusCode,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Standardized error response structure.
     *
     * @param string $code
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function errorResponse(string $code, string $message, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'statusCode' => $statusCode,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $statusCode);
    }

    /**
     * Validate phone number format (e.g. +919876543210 or 9876543210).
     *
     * @param mixed $phone
     * @return bool
     */
    protected function isValidPhoneNumber($phone): bool
    {
        if (empty($phone) || !is_string($phone)) {
            return false;
        }

        $phone = trim($phone);

        // Phone must be exactly 10 digits (no country code prefix)
        return (bool) preg_match('/^\d{10}$/', $phone);
    }
}
