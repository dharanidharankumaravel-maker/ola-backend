<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\CustomerToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerAuthService
{
    /**
     * Send OTP to phone number.
     *
     * Rules:
     *  - `name` and `email` are NEVER written or updated on the `users` table here.
     *    Only `phone`, `otp`, `otp_expires_at`, `fcm_key` are stored/updated.
     *  - The `customers` table is also created/updated on every sendOtp call.
     *
     * Flow:
     *  - Phone NOT in `users` → create minimal `User` (phone + otp + fcm_key only)
     *                          + create minimal `Customer` record.
     *  - Phone already in `users` → update `otp`, `otp_expires_at`, `fcm_key` on `User`
     *                              + update/touch the existing `Customer` record.
     *
     * @param string $phone
     * @param string $phone
     * @param string|null $fcmKey  FCM device token (optional)
     * @return array
     */
    public function sendOtp(string $phone, ?string $fcmKey = null): array
    {
        $otp       = (string) rand(1000, 9999);
        $expiresAt = now()->addSeconds(60);

        // ── 1. Users table — store only phone + otp (+ optional fcm_key) ──────
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            // New phone → create User with ONLY the given details (no name, no email)
            $userData = [
                'phone'          => $phone,
                'role'           => User::ROLE_CUSTOMER,
                'status'         => 'active',
                'password'       => Hash::make(Str::random(24)),
                'otp'            => $otp,
                'otp_expires_at' => $expiresAt,
            ];
            if (!empty($fcmKey)) {
                $userData['fcm_key'] = $fcmKey;
            }
            $user = User::create($userData);
        } else {
            // Existing phone → update ONLY otp (and fcm_key if provided); name & email are untouched
            $updateData = [
                'otp'            => $otp,
                'otp_expires_at' => $expiresAt,
            ];
            if (!empty($fcmKey)) {
                $updateData['fcm_key'] = $fcmKey;
            }
            $user->update($updateData);
        }

        // ── 2. Customers table — create minimal record or touch existing ────────
        $customer = Customer::where('phone', $phone)->first();

        if (!$customer) {
            // New customer — create minimal record with auto-incrementing customer_code
            Customer::create([
                'user_id'       => $user->id,
                'customer_code' => Customer::generateCustomerCode(),
                'phone'         => $phone,
                'status'        => 'active',
            ]);
        } else {
            // Existing customer — ensure customer_code exists
            if (empty($customer->customer_code)) {
                $customer->customer_code = Customer::generateCustomerCode();
                $customer->save();
            } else {
                $customer->touch();
            }
        }

        // ── 3. Resend-throttle window ──────────────────────────────────────────
        Cache::put("customer_otp_resend_{$phone}", now()->addSeconds(30)->timestamp, 30);

        return [
            'phone'             => $phone,
            'otp'               => $otp,
            'expiresIn'         => 60,
            'resendAvailableIn' => 30,
        ];
    }

    /**
     * Verify OTP and log in / register customer.
     *
     * OTP is validated against `users.otp` + `users.otp_expires_at` (DB-first).
     * Falls back to the in-memory cache. Testing bypass '1234' is kept.
     * On success, `fcm_key`, `device_name`, `device_type`, `device_os` are saved in `users`.
     * The `customers` record is created here (if not already present).
     *
     * @param string $phone
     * @param string $otp
     * @param string $fcmKey
     * @param string $deviceName
     * @param string $deviceType
     * @param string $deviceOs
     * @return array|null Returns [user, tokens, isNewUser] or null if OTP invalid
     */
    public function verifyOtp(
        string $phone,
        string $otp,
        string $fcmKey,
        string $deviceName,
        string $deviceType,
        string $deviceOs
    ): ?array
    {
        // ── 1. Find the user row by phone ─────────────────────────────────────
        $user = User::where('phone', $phone)->first();

        // ── 2. Validate OTP ───────────────────────────────────────────────────
        $isValidOtp = false;

        if ($user && $user->otp !== null) {
            // DB-based: check value and expiry
            $isValidOtp = ($user->otp === $otp)
                && ($user->otp_expires_at !== null)
                && $user->otp_expires_at->isFuture();
        }

        // Fallback: cache-based
        if (!$isValidOtp) {
            $cachedOtp  = Cache::get("customer_otp_{$phone}");
            $isValidOtp = ($cachedOtp !== null && $cachedOtp === $otp);
        }

        // Testing bypass
        if (!$isValidOtp && $otp === '1234') {
            $isValidOtp = true;
        }

        if (!$isValidOtp) {
            return null;
        }

        // ── 3. Clear OTP and store device/fcm info in users table ──────────────
        Cache::forget("customer_otp_{$phone}");

        if ($user) {
            $user->update([
                'otp'            => null,
                'otp_expires_at' => null,
                'fcm_key'        => $fcmKey,
                'device_name'    => $deviceName,
                'device_type'    => $deviceType,
                'device_os'      => $deviceOs,
            ]);
        } else {
            // Edge case: verify called without a prior sendOtp → ensure user row exists
            $user = User::create([
                'phone'       => $phone,
                'role'        => User::ROLE_CUSTOMER,
                'status'      => 'active',
                'password'    => Hash::make(Str::random(24)),
                'fcm_key'     => $fcmKey,
                'device_name' => $deviceName,
                'device_type' => $deviceType,
                'device_os'   => $deviceOs,
            ]);
        }

        // ── 4. Find or create Customer record ─────────────────────────────────
        $isNewUser = false;
        $customer  = Customer::where('phone', $phone)->first();

        if (!$customer) {
            $isNewUser = true;

            // Generate unique referral code
            $referralCode = strtoupper(Str::random(8));
            while (Customer::where('referral_code', $referralCode)->exists()) {
                $referralCode = strtoupper(Str::random(8));
            }

            $customer = Customer::create([
                'user_id'        => $user->id,
                'customer_code'  => Customer::generateCustomerCode(),
                'phone'          => $phone,
                'wallet_balance' => 0.00,
                'rating'         => 5.00,
                'total_rides'    => 0,
                'total_spent'    => 0.00,
                'referral_code'  => $referralCode,
                'status'         => 'active',
            ]);
        } else {
            // Ensure customer_code & referral code exist
            $dirty = false;
            if (empty($customer->customer_code)) {
                $customer->customer_code = Customer::generateCustomerCode();
                $dirty = true;
            }
            if (empty($customer->referral_code)) {
                $customer->referral_code = strtoupper(Str::random(8));
                $dirty = true;
            }
            if ($dirty) {
                $customer->save();
            }
        }

        if ($customer->status === 'blocked') {
            return ['blocked' => true];
        }

        $tokens = $this->generateTokens($customer);

        return [
            'user'      => $this->formatCustomerProfile($customer),
            'tokens'    => $tokens,
            'isNewUser' => $isNewUser,
        ];
    }



    /**
     * Generate access and refresh tokens for customer.
     *
     * @param Customer $customer
     * @return array
     */
    public function generateTokens(Customer $customer): array
    {
        $now = time();
        $accessTtl = 86400 * 7; // 7 days
        $refreshTtl = 86400 * 30; // 30 days

        $accessJti = Str::random(32);
        $refreshJti = Str::random(32);

        $accessPayload = [
            'iss' => 'ola-customer-api',
            'sub' => $customer->id,
            'user_id' => $customer->user_id,
            'phone' => $customer->phone,
            'type' => 'access',
            'jti' => $accessJti,
            'iat' => $now,
            'exp' => $now + $accessTtl,
        ];

        $refreshPayload = [
            'iss' => 'ola-customer-api',
            'sub' => $customer->id,
            'user_id' => $customer->user_id,
            'phone' => $customer->phone,
            'type' => 'refresh',
            'jti' => $refreshJti,
            'iat' => $now,
            'exp' => $now + $refreshTtl,
        ];

        $accessToken = $this->createJwt($accessPayload);
        $refreshToken = $this->createJwt($refreshPayload);

        // Store refresh token record in customer_tokens table
        CustomerToken::create([
            'customer_id' => $customer->id,
            'token_id' => $refreshJti,
            'token_type' => 'refresh',
            'token_hash' => hash('sha256', $refreshToken),
            'is_revoked' => false,
            'expires_at' => Carbon::createFromTimestamp($refreshPayload['exp']),
        ]);

        return [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
        ];
    }

    /**
     * Refresh access and refresh tokens using valid refresh token.
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

        $tokenRecord = CustomerToken::where('token_id', $jti)
            ->where('token_type', 'refresh')
            ->first();

        if (!$tokenRecord || $tokenRecord->is_revoked || $tokenRecord->expires_at->isPast()) {
            return null;
        }

        $customer = Customer::find($payload['sub'] ?? null);
        if (!$customer || $customer->status === 'blocked') {
            return null;
        }

        // Revoke the used refresh token (token rotation)
        $tokenRecord->update(['is_revoked' => true]);

        // Generate new token pair
        return $this->generateTokens($customer);
    }

    /**
     * Invalidate session and tokens on logout.
     *
     * @param Customer|null $customer
     * @param string|null $refreshToken
     * @param string|null $accessToken
     * @return bool
     */
    public function logout(?Customer $customer, ?string $refreshToken = null, ?string $accessToken = null): bool
    {
        if ($refreshToken) {
            $refreshPayload = $this->verifyJwt($refreshToken, false);
            if ($refreshPayload && isset($refreshPayload['jti'])) {
                CustomerToken::where('token_id', $refreshPayload['jti'])->update(['is_revoked' => true]);
            }
            CustomerToken::where('token_hash', hash('sha256', $refreshToken))->update(['is_revoked' => true]);
        }

        if ($accessToken) {
            $accessPayload = $this->verifyJwt($accessToken, false);
            if ($accessPayload && isset($accessPayload['jti'])) {
                $exp = $accessPayload['exp'] ?? (time() + 3600);
                $ttl = max(1, $exp - time());
                Cache::put("revoked_token_{$accessPayload['jti']}", true, $ttl);
            }
        }

        if ($customer) {
            // Also revoke active refresh tokens for this customer
            CustomerToken::where('customer_id', $customer->id)
                ->where('token_type', 'refresh')
                ->where('is_revoked', false)
                ->update(['is_revoked' => true]);

            // Clear FCM key from user table
            if ($customer->user) {
                $customer->user->update(['fcm_key' => null]);
            }
        }

        return true;
    }

    /**
     * Validate an access token and return the associated Customer.
     *
     * @param string $accessToken
     * @return Customer|null
     */
    public function validateAccessToken(string $accessToken): ?Customer
    {
        $payload = $this->verifyJwt($accessToken);
        if (!$payload || ($payload['type'] ?? '') !== 'access') {
            return null;
        }

        $jti = $payload['jti'] ?? null;
        if ($jti && Cache::has("revoked_token_{$jti}")) {
            return null;
        }

        $customerId = $payload['sub'] ?? null;
        if (!$customerId) {
            return null;
        }

        $customer = Customer::find($customerId);
        if (!$customer || $customer->status === 'blocked') {
            return null;
        }

        return $customer;
    }

    /**
     * Format Customer model into the standardized API user profile structure.
     *
     * @param Customer $customer
     * @return array
     */
    public function formatCustomerProfile(Customer $customer): array
    {
        $notifs = $customer->notification_prefs;
        if (is_string($notifs)) {
            $notifs = json_decode($notifs, true);
        }
        $locs = $customer->location_prefs;
        if (is_string($locs)) {
            $locs = json_decode($locs, true);
        }

        return [
            'id'                => $this->formatUserId($customer),
            'customerCode'      => $customer->customer_code,
            'phone'             => $customer->phone,
            'name'              => $customer->name,
            'email'             => $customer->email,
            'avatar'            => $customer->avatar ?: 'Felix',
            'profilePhoto'      => $customer->avatar ?: 'Felix',            
            'emergency_contact' => $customer->emergency_contact,
            'emergencyContact'  => $customer->emergency_contact,
            'notificationPrefs' => $notifs ?? [],
            'locationPrefs'     => $locs ?? [],
            'preferences'       => [
                'notifications' => $notifs ?? [],
                'location'      => $locs ?? [],
            ],
            'rating'            => (float) number_format((float) $customer->rating, 1, '.', ''),
            'walletBalance'     => (float) number_format((float) $customer->wallet_balance, 2, '.', ''),
            'totalRides'        => (int) $customer->total_rides,
            'referralCode'      => $customer->referral_code ?: 'VERON123',
            'createdAt'         => $customer->created_at ? $customer->created_at->format('Y-m-d\TH:i:s\Z') : now()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * Format user ID string, e.g. usr_9876543210.
     *
     * @param Customer $customer
     * @return string
     */
    public function formatUserId(Customer $customer): string
    {
        $digits = preg_replace('/\D/', '', $customer->phone ?? '');
        if (strlen($digits) > 10 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        return 'usr_' . ($digits ?: $customer->id);
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

    /**
     * Get secret signing key from application config.
     *
     * @return string
     */
    protected function getSecretKey(): string
    {
        $key = config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return $key ?: 'default-secret-salt-key-ola';
    }

    /**
     * Base64 URL encode according to RFC 7515.
     *
     * @param string $data
     * @return string
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode according to RFC 7515.
     *
     * @param string $data
     * @return string
     */
    protected function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
