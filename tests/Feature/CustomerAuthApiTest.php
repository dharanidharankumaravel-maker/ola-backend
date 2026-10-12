<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAuthApiTest extends TestCase
{
    use DatabaseTransactions;

    protected Customer $customer;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Dharani',
            'email' => 'dharani@example.com',
            'phone' => '9876543210',
            'role' => 'customer',
            'status' => 'active',
            'password' => Hash::make('secret123'),
        ]);

        $this->customer = new Customer([
            'user_id' => $this->user->id,
            'customer_code' => 'CUST-00001',
            'name' => 'Dharani',
            'email' => 'dharani@example.com',
            'phone' => '9876543210',
            'avatar' => 'Felix',
            'rating' => 4.80,
            'wallet_balance' => 250.00,
            'total_rides' => 14,
            'total_spent' => 1250.00,
            'referral_code' => 'VERON123',
            'status' => 'active',
        ]);
        $this->customer->timestamps = false;
        $this->customer->created_at = '2026-01-15 10:30:00';
        $this->customer->save();
        $this->customer->timestamps = true;
    }

    public function test_send_otp_success_without_fcm_key(): void
    {
        $response = $this->postJson('/api/customer/auth/otp/send', [
            'phone' => '9876543210',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statusCode' => 200,
                'message' => 'OTP sent successfully',
                'data' => [
                    'phone' => '9876543210',
                    'expiresIn' => 60,
                    'resendAvailableIn' => 30,
                ],
            ]);

        $this->assertNotEmpty($response->json('data.otp'));
    }

    public function test_send_otp_success_with_fcm_key(): void
    {
        $response = $this->postJson('/api/customer/auth/otp/send', [
            'phone' => '9876543210',
            'fcm_key' => 'sample_fcm_key_123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statusCode' => 200,
                'message' => 'OTP sent successfully',
                'data' => [
                    'phone' => '9876543210',
                ],
            ]);
    }

    public function test_send_otp_invalid_phone_format(): void
    {
        $response = $this->postJson('/api/customer/auth/otp/send', [
            'phone' => 'invalid-phone',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'statusCode' => 400,
                'error' => [
                    'code' => 'INVALID_PHONE_NUMBER',
                    'message' => 'The phone number format provided is invalid.',
                ],
            ]);
    }

    public function test_verify_otp_validation_requires_fcm_and_device_info(): void
    {
        // Missing fcm_key
        $res1 = $this->postJson('/api/customer/auth/otp/verify', [
            'phone' => '9876543210',
            'otp' => '1234',
        ]);
        $res1->assertStatus(400)->assertJson([
            'error' => ['code' => 'FCM_KEY_REQUIRED'],
        ]);

        // Missing device_name
        $res2 = $this->postJson('/api/customer/auth/otp/verify', [
            'phone' => '9876543210',
            'otp' => '1234',
            'fcm_key' => 'test_fcm_key',
        ]);
        $res2->assertStatus(400)->assertJson([
            'error' => ['code' => 'DEVICE_NAME_REQUIRED'],
        ]);

        // Missing device_type
        $res3 = $this->postJson('/api/customer/auth/otp/verify', [
            'phone' => '9876543210',
            'otp' => '1234',
            'fcm_key' => 'test_fcm_key',
            'device_name' => 'Pixel 8',
        ]);
        $res3->assertStatus(400)->assertJson([
            'error' => ['code' => 'DEVICE_TYPE_REQUIRED'],
        ]);

        // Missing device_os
        $res4 = $this->postJson('/api/customer/auth/otp/verify', [
            'phone' => '9876543210',
            'otp' => '1234',
            'fcm_key' => 'test_fcm_key',
            'device_name' => 'Pixel 8',
            'device_type' => 'android',
        ]);
        $res4->assertStatus(400)->assertJson([
            'error' => ['code' => 'DEVICE_OS_REQUIRED'],
        ]);
    }

    public function test_verify_otp_success_existing_customer_stores_device_info(): void
    {
        $response = $this->postJson('/api/customer/auth/otp/verify', [
            'phone' => '9876543210',
            'otp' => '1234',
            'fcm_key' => 'fcm_test_token_abc',
            'device_name' => 'Samsung S24',
            'device_type' => 'android',
            'device_os' => 'Android 14',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Authentication successful',
                'data' => [
                    'user' => [
                        'id' => 'usr_9876543210',
                        'phone' => '9876543210',
                        'name' => 'Dharani',
                        'email' => 'dharani@example.com',
                        'profilePhoto' => 'Felix',
                        'rating' => 4.8,
                        'walletBalance' => 250,
                        'totalRides' => 14,
                        'referralCode' => 'VERON123',
                        'createdAt' => '2026-01-15T10:30:00Z',
                    ],
                    'isNewUser' => false,
                ],
            ]);

        $this->assertNotEmpty($response->json('data.tokens.accessToken'));
        $this->assertNotEmpty($response->json('data.tokens.refreshToken'));

        // Verify stored in users table
        $this->assertDatabaseHas('users', [
            'phone' => '9876543210',
            'fcm_key' => 'fcm_test_token_abc',
            'device_name' => 'Samsung S24',
            'device_type' => 'android',
            'device_os' => 'Android 14',
            'otp' => null,
        ]);
    }

    public function test_verify_otp_registers_new_user_with_customer_code(): void
    {
        $newPhone = '9123456780';

        $response = $this->postJson('/api/customer/auth/otp/verify', [
            'phone' => $newPhone,
            'otp' => '1234',
            'fcm_key' => 'fcm_new_user_123',
            'device_name' => 'iPhone 15 Pro',
            'device_type' => 'ios',
            'device_os' => 'iOS 17.4',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Authentication successful',
                'data' => [
                    'user' => [
                        'id' => 'usr_9123456780',
                        'phone' => $newPhone,
                    ],
                    'isNewUser' => true,
                ],
            ]);

        // User row must have device info and fcm_key
        $this->assertDatabaseHas('users', [
            'phone' => $newPhone,
            'fcm_key' => 'fcm_new_user_123',
            'device_name' => 'iPhone 15 Pro',
            'device_type' => 'ios',
            'device_os' => 'iOS 17.4',
        ]);

        // Customer row must exist with auto-generated customer_code matching CUST-XXXXX
        $newCustomer = Customer::where('phone', $newPhone)->first();
        $this->assertNotNull($newCustomer);
        $this->assertMatchesRegularExpression('/^CUST-\d{5}$/', $newCustomer->customer_code);
    }

    public function test_customer_code_auto_increments_format(): void
    {
        $code1 = Customer::generateCustomerCode();
        $this->assertMatchesRegularExpression('/^CUST-\d{5}$/', $code1);
    }

    public function test_verify_otp_invalid_otp(): void
    {
        $response = $this->postJson('/api/customer/auth/otp/verify', [
            'phone' => '9876543210',
            'otp' => '0000',
            'fcm_key' => 'fcm_test',
            'device_name' => 'Pixel 8',
            'device_type' => 'android',
            'device_os' => 'Android 14',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'statusCode' => 400,
                'error' => [
                    'code' => 'INVALID_OTP',
                    'message' => 'The OTP provided is invalid or has expired.',
                ],
            ]);
    }

    public function test_refresh_token_success(): void
    {
        $loginResponse = $this->postJson('/api/customer/auth/otp/verify', [
            'phone' => '9876543210',
            'otp' => '1234',
            'fcm_key' => 'fcm_key_refresh',
            'device_name' => 'Pixel 8',
            'device_type' => 'android',
            'device_os' => 'Android 14',
        ]);

        $refreshToken = $loginResponse->json('data.tokens.refreshToken');

        $refreshResponse = $this->postJson('/api/customer/auth/refresh', [
            'refreshToken' => $refreshToken,
        ]);

        $refreshResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statusCode' => 200,
            ]);

        $this->assertNotEmpty($refreshResponse->json('data.accessToken'));
        $this->assertNotEmpty($refreshResponse->json('data.refreshToken'));
    }

    public function test_refresh_token_invalid(): void
    {
        $response = $this->postJson('/api/customer/auth/refresh', [
            'refreshToken' => 'invalid.jwt.token',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'statusCode' => 400,
                'error' => [
                    'code' => 'INVALID_REFRESH_TOKEN',
                ],
            ]);
    }

    public function test_get_me_profile_success(): void
    {
        $loginResponse = $this->postJson('/api/customer/auth/otp/verify', [
            'phone' => '9876543210',
            'otp' => '1234',
            'fcm_key' => 'fcm_key_me',
            'device_name' => 'Pixel 8',
            'device_type' => 'android',
            'device_os' => 'Android 14',
        ]);

        $accessToken = $loginResponse->json('data.tokens.accessToken');

        $response = $this->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->getJson('/api/customer/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statusCode' => 200,
                'data' => [
                    'id' => 'usr_9876543210',
                    'customerCode' => 'CUST-00001',
                    'phone' => '9876543210',
                    'name' => 'Dharani',
                    'email' => 'dharani@example.com',
                    'profilePhoto' => 'Felix',
                    'rating' => 4.8,
                    'walletBalance' => 250,
                    'totalRides' => 14,
                    'referralCode' => 'VERON123',
                    'createdAt' => '2026-01-15T10:30:00Z',
                ],
            ]);
    }

    public function test_get_me_unauthorized(): void
    {
        $response = $this->getJson('/api/customer/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'statusCode' => 401,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                ],
            ]);
    }

    public function test_logout_success(): void
    {
        $loginResponse = $this->postJson('/api/customer/auth/otp/verify', [
            'phone' => '9876543210',
            'otp' => '1234',
            'fcm_key' => 'fcm_key_logout',
            'device_name' => 'Pixel 8',
            'device_type' => 'android',
            'device_os' => 'Android 14',
        ]);

        $accessToken = $loginResponse->json('data.tokens.accessToken');
        $refreshToken = $loginResponse->json('data.tokens.refreshToken');

        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->postJson('/api/customer/auth/logout', [
                'refreshToken' => $refreshToken,
            ]);

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Logged out successfully',
            ]);

        // Attempting to refresh with the revoked refresh token must now fail
        $reRefreshResponse = $this->postJson('/api/customer/auth/refresh', [
            'refreshToken' => $refreshToken,
        ]);

        $reRefreshResponse->assertStatus(400)
            ->assertJson([
                'success' => false,
                'statusCode' => 400,
                'error' => [
                    'code' => 'INVALID_REFRESH_TOKEN',
                ],
            ]);
    }

    public function test_routes_accessible_via_auth_prefix_alias(): void
    {
        $response = $this->postJson('/api/auth/otp/send', [
            'phone' => '9876543210',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statusCode' => 200,
                'message' => 'OTP sent successfully',
            ]);
    }
}
