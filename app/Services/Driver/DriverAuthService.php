<?php

namespace App\Services\Driver;

use App\Models\Driver;
use App\Models\DriverToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DriverAuthService
{
    /**
     * Send OTP to driver phone.
     *
     * @param string $phoneNumber
     * @param string $countryCode
     * @return array
     */
    public function sendOtp(string $phoneNumber, string $countryCode = '+91'): array
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        // Ensure 10 digits if longer with 91 prefix
        if (strlen($cleanPhone) > 10 && str_starts_with($cleanPhone, '91')) {
            $cleanPhone = substr($cleanPhone, 2);
        }

        $otp = (string) rand(100000, 999999);
        $expiresIn = 120; // 120 seconds
        $expiresAt = now()->addSeconds($expiresIn);

        // Find or create User
        $user = User::where('phone', $cleanPhone)->first();
        if (!$user) {
            $user = User::create([
                'phone' => $cleanPhone,
                'role' => User::ROLE_DRIVER,
                'status' => 'active',
                'password' => Hash::make(Str::random(24)),
                'otp' => $otp,
                'otp_expires_at' => $expiresAt,
            ]);
        } else {
            $user->update([
                'otp' => $otp,
                'otp_expires_at' => $expiresAt,
            ]);
        }

        // Find or create Driver
        $driver = Driver::where('phone', $cleanPhone)->first();
        if (!$driver) {
            $driverCode = Driver::generateDriverCode();
            $driver = Driver::create([
                'user_id' => $user->id,
                'driver_code' => $driverCode,
                'phone' => $cleanPhone,
                'status' => 'pending',
                'online_status' => 'offline',
                'rating' => 5.00,
                'wallet_balance' => 0.00,
                'total_earnings' => 0.00,
                'total_trips' => 0,
            ]);
        } elseif (!$driver->user_id) {
            $driver->update(['user_id' => $user->id]);
        }

        // Generate verificationId
        $verificationId = 'v_req_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);

        Cache::put("driver_verification_{$verificationId}", [
            'phoneNumber' => $cleanPhone,
            'countryCode' => $countryCode,
            'otp' => $otp,
        ], $expiresIn);

        return [
            'verificationId' => $verificationId,
            'expiresIn' => $expiresIn,
            'otp' => $otp, // Sent in response as requested by user
        ];
    }

    /**
     * Verify OTP and authenticate driver.
     *
     * @param string $phoneNumber
     * @param string $otp
     * @param string|null $verificationId
     * @param string $countryCode
     * @return array|null
     */
    public function verifyOtp(
        string $phoneNumber,
        string $otp,
        ?string $verificationId = null,
        string $countryCode = '+91'
    ): ?array {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (strlen($cleanPhone) > 10 && str_starts_with($cleanPhone, '91')) {
            $cleanPhone = substr($cleanPhone, 2);
        }

        $isValid = false;

        // 1. Check cached verification session if verificationId provided
        if ($verificationId) {
            $cached = Cache::get("driver_verification_{$verificationId}");
            if ($cached && isset($cached['otp']) && $cached['otp'] === $otp) {
                $isValid = true;
            }
        }

        // 2. Check User DB record
        $user = User::where('phone', $cleanPhone)->first();
        if (!$isValid && $user && $user->otp !== null) {
            if ($user->otp === $otp && $user->otp_expires_at && $user->otp_expires_at->isFuture()) {
                $isValid = true;
            }
        }

        // 3. Testing bypass
        if (!$isValid && ($otp === '123456' || $otp === '1234')) {
            $isValid = true;
        }

        if (!$isValid) {
            return null;
        }

        // Clear OTP
        if ($verificationId) {
            Cache::forget("driver_verification_{$verificationId}");
        }
        if ($user) {
            $user->update([
                'otp' => null,
                'otp_expires_at' => null,
            ]);
        } else {
            $user = User::create([
                'phone' => $cleanPhone,
                'role' => User::ROLE_DRIVER,
                'status' => 'active',
                'password' => Hash::make(Str::random(24)),
            ]);
        }

        // Find or create Driver
        $driver = Driver::where('phone', $cleanPhone)->first();
        if (!$driver) {
            $driverCode = Driver::generateDriverCode();
            $driver = Driver::create([
                'user_id' => $user->id,
                'driver_code' => $driverCode,
                'phone' => $cleanPhone,
                'status' => 'pending',
                'online_status' => 'offline',
                'rating' => 5.00,
                'wallet_balance' => 0.00,
                'total_earnings' => 0.00,
                'total_trips' => 0,
            ]);
        }

        if ($driver->status === 'blocked') {
            return ['blocked' => true];
        }

        $hasCompletedRegistration = (
            (!empty($driver->first_name) || !empty($driver->name))
            && !empty($driver->email)
            && !empty($driver->vehicle_id)
        );

        $isApprovedByAdmin = ($driver->status === 'approved');

        $tokens = $this->generateTokens($driver);

        $statusStr = match ($driver->status) {
            'approved' => 'APPROVED',
            'pending' => 'PENDING_VERIFICATION',
            'rejected' => 'REJECTED',
            'blocked' => 'BLOCKED',
            default => 'PENDING_VERIFICATION',
        };

        return [
            'isLoggedIn' => true,
            'hasCompletedRegistration' => $hasCompletedRegistration,
            'isApprovedByAdmin' => $isApprovedByAdmin,
            'tokens' => $tokens,
            'driver' => [
                'id' => $driver->driver_code ?: ('drv_' . $driver->id),
                'phoneNumber' => $driver->phone,
                'status' => $statusStr,
            ],
        ];
    }

    /**
     * Generate Access & Refresh tokens.
     *
     * @param Driver $driver
     * @return array
     */
    public function generateTokens(Driver $driver): array
    {
        $now = time();
        $accessTtl = 3600; // 1 hour per spec
        $refreshTtl = 86400 * 30; // 30 days

        $accessJti = Str::random(32);
        $refreshJti = Str::random(32);

        $accessPayload = [
            'iss' => 'ola-driver-api',
            'sub' => $driver->id,
            'user_id' => $driver->user_id,
            'phone' => $driver->phone,
            'type' => 'access',
            'jti' => $accessJti,
            'iat' => $now,
            'exp' => $now + $accessTtl,
        ];

        $refreshPayload = [
            'iss' => 'ola-driver-api',
            'sub' => $driver->id,
            'user_id' => $driver->user_id,
            'phone' => $driver->phone,
            'type' => 'refresh',
            'jti' => $refreshJti,
            'iat' => $now,
            'exp' => $now + $refreshTtl,
        ];

        $accessToken = $this->createJwt($accessPayload);
        $refreshToken = $this->createJwt($refreshPayload);

        DriverToken::create([
            'driver_id' => $driver->id,
            'token_id' => $refreshJti,
            'token_type' => 'refresh',
            'token_hash' => hash('sha256', $refreshToken),
            'is_revoked' => false,
            'expires_at' => Carbon::createFromTimestamp($refreshPayload['exp']),
        ]);

        return [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'expiresIn' => $accessTtl,
        ];
    }

    /**
     * Refresh tokens using a valid refresh token.
     *
     * @param string $refreshToken
     * @return array|null
     */
    public function refreshTokens(string $refreshToken): ?array
    {
        $payload = $this->verifyJwt($refreshToken);
        if (!$payload || ($payload['type'] ?? '') !== 'refresh') {
            return null;
        }

        $jti = $payload['jti'] ?? null;
        if (!$jti) {
            return null;
        }

        $tokenRecord = DriverToken::where('token_id', $jti)
            ->where('token_type', 'refresh')
            ->first();

        if (!$tokenRecord || $tokenRecord->is_revoked || $tokenRecord->expires_at->isPast()) {
            return null;
        }

        $driver = Driver::find($payload['sub'] ?? null);
        if (!$driver || $driver->status === 'blocked') {
            return null;
        }

        // Revoke the old token
        $tokenRecord->update(['is_revoked' => true]);

        return $this->generateTokens($driver);
    }

    /**
     * Invalidate session and mark driver offline.
     *
     * @param Driver|null $driver
     * @param string|null $accessToken
     * @return bool
     */
    public function logout(?Driver $driver, ?string $accessToken = null): bool
    {
        if ($accessToken) {
            $accessPayload = $this->verifyJwt($accessToken, false);
            if ($accessPayload && isset($accessPayload['jti'])) {
                $exp = $accessPayload['exp'] ?? (time() + 3600);
                $ttl = max(1, $exp - time());
                Cache::put("revoked_driver_token_{$accessPayload['jti']}", true, $ttl);
            }
        }

        if ($driver) {
            DriverToken::where('driver_id', $driver->id)
                ->where('token_type', 'refresh')
                ->where('is_revoked', false)
                ->update(['is_revoked' => true]);

            $driver->update([
                'online_status' => 'offline',
            ]);

            if ($driver->user) {
                $driver->user->update(['fcm_key' => null]);
            }
        }

        return true;
    }

    /**
     * Validate an access token and return the Driver.
     *
     * @param string $accessToken
     * @return Driver|null
     */
    public function validateAccessToken(string $accessToken): ?Driver
    {
        $payload = $this->verifyJwt($accessToken);
        if (!$payload || ($payload['type'] ?? '') !== 'access') {
            return null;
        }

        $jti = $payload['jti'] ?? null;
        if ($jti && Cache::has("revoked_driver_token_{$jti}")) {
            return null;
        }

        $driverId = $payload['sub'] ?? null;
        if (!$driverId) {
            return null;
        }

        $driver = Driver::with('vehicle')->find($driverId);
        if (!$driver || $driver->status === 'blocked') {
            return null;
        }

        return $driver;
    }

    /**
     * Create signed JWT.
     *
     * @param array $payload
     * @return string
     */
    protected function createJwt(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $secret = $this->getSecretKey();
        $signature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $secret, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    /**
     * Verify signed JWT and return payload.
     *
     * @param string $jwt
     * @param bool $checkExpiry
     * @return array|null
     */
    public function verifyJwt(string $jwt, bool $checkExpiry = true): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        $secret = $this->getSecretKey();
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $secret, true)
        );

        if (!hash_equals($expectedSignature, $signatureEncoded)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);
        if (!is_array($payload)) {
            return null;
        }

        if ($checkExpiry && isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    protected function getSecretKey(): string
    {
        $key = config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return $key ?: 'default-secret-salt-key-ola-driver';
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
