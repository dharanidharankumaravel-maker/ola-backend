<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverApiTest extends TestCase
{
    use DatabaseTransactions;

    protected string $testPhone = '9876543210';

    protected function createAuthenticatedDriver(): array
    {
        $user = User::create([
            'name'     => 'Rajesh Kumar',
            'email'    => 'rajesh.' . uniqid() . '@example.com',
            'phone'    => '98' . rand(10000000, 99999999),
            'role'     => User::ROLE_DRIVER,
            'status'   => 'active',
            'password' => bcrypt('password123'),
        ]);

        $vehicle = Vehicle::create([
            'reg_number'   => 'MH 12 ' . strtoupper(uniqid()),
            'vehicle_type' => 'sedan',
            'make'         => 'Maruti Suzuki',
            'model'        => 'Dzire',
            'year'         => '2022',
            'color'        => 'White',
            'owner_name'   => 'Rajesh Kumar',
            'status'       => 'active',
        ]);

        $driver = Driver::create([
            'user_id'             => $user->id,
            'driver_code'         => Driver::generateDriverCode(),
            'name'                => 'Rajesh Kumar',
            'first_name'          => 'Rajesh',
            'last_name'           => 'Kumar',
            'email'               => $user->email,
            'phone'               => $user->phone,
            'vehicle_id'          => $vehicle->id,
            'status'              => 'approved',
            'online_status'       => 'online',
            'wallet_balance'      => 2450.00,
            'total_earnings'      => 32100.00,
            'total_trips'         => 215,
            'rating'              => 4.85,
            'bank_name'           => 'HDFC Bank',
            'account_number'      => '50100293819201',
            'ifsc_code'           => 'HDFC0001234',
            'account_holder_name' => 'Rajesh Kumar',
            'dl_expiry_date'      => now()->addYears(2),
            'insurance_expiry_date' => now()->addYear(),
        ]);

        $vehicle->update(['driver_id' => $driver->id]);

        $authService = app(\App\Services\Driver\DriverAuthService::class);
        $tokens = $authService->generateTokens($driver);

        return [
            'user'         => $user,
            'driver'       => $driver,
            'vehicle'      => $vehicle,
            'accessToken'  => $tokens['accessToken'],
            'refreshToken' => $tokens['refreshToken'],
        ];
    }

    public function test_send_otp_success_returns_otp_in_response(): void
    {
        $response = $this->postJson('/api/auth/send-otp', [
            'phoneNumber' => $this->testPhone,
            'countryCode' => '+91',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'    => true,
                'statusCode' => 200,
                'message'    => 'OTP sent successfully.',
            ])
            ->assertJsonStructure([
                'success',
                'statusCode',
                'message',
                'data' => [
                    'verificationId',
                    'expiresIn',
                    'otp',
                ],
            ]);

        // Verify OTP is present in data
        $otp = $response->json('data.otp');
        $this->assertNotEmpty($otp);
        $this->assertEquals(6, strlen($otp));
    }

    public function test_verify_otp_authenticates_driver(): void
    {
        // 1. Send OTP
        $sendResponse = $this->postJson('/api/auth/send-otp', [
            'phoneNumber' => '9988776655',
            'countryCode' => '+91',
        ]);
        $verificationId = $sendResponse->json('data.verificationId');
        $otp = $sendResponse->json('data.otp');

        // 2. Verify OTP
        $verifyResponse = $this->postJson('/api/auth/verify-otp', [
            'phoneNumber'    => '9988776655',
            'countryCode'    => '+91',
            'otp'            => $otp,
            'verificationId' => $verificationId,
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJson([
                'success'    => true,
                'statusCode' => 200,
                'data' => [
                    'isLoggedIn' => true,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'statusCode',
                'data' => [
                    'isLoggedIn',
                    'hasCompletedRegistration',
                    'isApprovedByAdmin',
                    'tokens' => [
                        'accessToken',
                        'refreshToken',
                        'expiresIn',
                    ],
                    'driver' => [
                        'id',
                        'phoneNumber',
                        'status',
                    ],
                ],
            ]);
    }

    public function test_refresh_token_and_logout(): void
    {
        $setup = $this->createAuthenticatedDriver();

        // Refresh token
        $refreshRes = $this->postJson('/api/auth/refresh-token', [
            'refreshToken' => $setup['refreshToken'],
        ]);

        $refreshRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statusCode' => 200,
            ])
            ->assertJsonStructure([
                'data' => ['accessToken', 'refreshToken', 'expiresIn'],
            ]);

        // Logout
        $logoutRes = $this->withHeader('Authorization', 'Bearer ' . $setup['accessToken'])
            ->postJson('/api/auth/logout');

        $logoutRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Logged out successfully.',
            ]);
    }

    public function test_driver_profile_lifecycle(): void
    {
        $setup = $this->createAuthenticatedDriver();
        $headers = ['Authorization' => 'Bearer ' . $setup['accessToken']];

        // 1. Post Initial Profile
        $profileRes = $this->withHeaders($headers)->postJson('/api/driver/profile', [
            'firstName' => 'Rajesh',
            'lastName'  => 'Kumar',
            'email'     => 'rajesh.updated.' . uniqid() . '@example.com',
            'address'   => '12, 1st Cross Street, KK Nagar, Madurai',
        ]);

        $profileRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statusCode' => 200,
                'data' => [
                    'firstName' => 'Rajesh',
                    'lastName'  => 'Kumar',
                    'fullName'  => 'Rajesh Kumar',
                ],
            ]);

        // 2. Get Profile
        $getProfileRes = $this->withHeaders($headers)->getJson('/api/driver/profile');
        $getProfileRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statusCode' => 200,
                'data' => [
                    'firstName' => 'Rajesh',
                    'lastName'  => 'Kumar',
                    'bankName'  => 'HDFC Bank',
                ],
            ]);

        // 3. Put Update Profile
        $putProfileRes = $this->withHeaders($headers)->putJson('/api/driver/profile', [
            'firstName' => 'Rajesh',
            'lastName'  => 'Sharma',
            'email'     => 'rajesh.new.' . uniqid() . '@example.com',
        ]);
        $putProfileRes->assertStatus(200)
            ->assertJson([
                'data' => [
                    'firstName' => 'Rajesh',
                    'lastName'  => 'Sharma',
                    'fullName'  => 'Rajesh Sharma',
                ],
            ]);
    }

    public function test_document_uploads_and_renew(): void
    {
        Storage::fake('public');
        $setup = $this->createAuthenticatedDriver();
        $headers = ['Authorization' => 'Bearer ' . $setup['accessToken']];

        // Personal KYC
        $docRes = $this->withHeaders($headers)->postJson('/api/driver/documents/personal', [
            'documentType'   => 'DL',
            'documentNumber' => 'DL-TN59-20180004921',
            'expiryDate'     => '2028-06-15',
        ]);

        $docRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'documentType'       => 'DL',
                    'verificationStatus' => 'PENDING_VERIFICATION',
                ],
            ]);

        // Renew document
        $renewRes = $this->withHeaders($headers)->postJson('/api/driver/documents/renew', [
            'documentType' => 'Driving License',
            'expiryDate'   => '2027-10-01',
        ]);

        $renewRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Document submitted for verification.',
                'data' => [
                    'status'       => 'UNDER_REVIEW',
                    'documentType' => 'Driving License',
                ],
            ]);
    }

    public function test_vehicle_management_and_documents(): void
    {
        $setup = $this->createAuthenticatedDriver();
        $headers = ['Authorization' => 'Bearer ' . $setup['accessToken']];

        // Register Vehicle
        $vehRes = $this->withHeaders($headers)->postJson('/api/driver/vehicle', [
            'serviceType'      => 'Car / Prime Sedan',
            'brand'            => 'Maruti Suzuki',
            'model'            => 'Dzire',
            'manufacturerYear' => '2022',
            'numberPlate'      => 'MH 12 CD ' . rand(1000, 9999),
            'color'            => 'White',
        ]);

        $vehRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'statusCode' => 201,
                'data' => [
                    'serviceType'  => 'Car / Prime Sedan',
                    'vehicleModel' => 'Maruti Suzuki Dzire',
                    'status'       => 'PENDING_DOCS',
                ],
            ]);

        // Vehicle Document Upload
        $vehDocRes = $this->withHeaders($headers)->postJson('/api/driver/vehicle/documents', [
            'vehicleId'          => 'veh_49102',
            'documentCategory'   => 'INSURANCE',
            'policyOrCertNumber' => 'POL-9921448-ICICI',
            'expiryDate'         => '2027-09-20',
        ]);

        $vehDocRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'documentCategory'   => 'INSURANCE',
                    'status'             => 'UPLOADED',
                    'verificationStatus' => 'PENDING_APPROVAL',
                ],
            ]);
    }

    public function test_status_toggle_and_telemetry(): void
    {
        $setup = $this->createAuthenticatedDriver();
        $headers = ['Authorization' => 'Bearer ' . $setup['accessToken']];

        // Toggle Status
        $toggleRes = $this->withHeaders($headers)->postJson('/api/driver/status/toggle', [
            'isOnline'   => true,
            'currentLat' => 9.9252,
            'currentLng' => 78.1198,
        ]);

        $toggleRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'isOnline' => true,
                    'status'   => 'SEARCHING_FOR_RIDES',
                ],
            ]);

        // Telemetry batch update
        $telemetryRes = $this->withHeaders($headers)->postJson('/api/driver/telemetry/location', [
            'locations' => [
                [
                    'latitude'  => 9.9252,
                    'longitude' => 78.1198,
                    'bearing'   => 90.0,
                    'speed'     => 28.5,
                    'timestamp' => 1727690200000,
                ],
            ],
        ]);

        $telemetryRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statusCode' => 200,
            ]);
    }

    public function test_ride_dispatch_lifecycle(): void
    {
        $setup = $this->createAuthenticatedDriver();
        $headers = ['Authorization' => 'Bearer ' . $setup['accessToken']];

        // Create a customer and ride
        $customerUser = User::create([
            'phone'    => '91' . rand(10000000, 99999999),
            'role'     => User::ROLE_CUSTOMER,
            'password' => bcrypt('secret'),
        ]);
        $customer = Customer::create([
            'user_id'       => $customerUser->id,
            'customer_code' => Customer::generateCustomerCode(),
            'name'          => 'Aarav Sharma',
            'phone'         => $customerUser->phone,
        ]);

        $ride = Ride::create([
            'ride_number'      => 'RIDE-8421',
            'customer_id'      => $customer->id,
            'vehicle_type'     => 'sedan',
            'pickup_address'   => 'Anna Nagar, Madurai',
            'pickup_lat'       => 9.9252,
            'pickup_lng'       => 78.1198,
            'dropoff_address'  => 'K.K. Nagar, Madurai',
            'dropoff_lat'      => 9.9320,
            'dropoff_lng'      => 78.1350,
            'distance_km'      => 6.2,
            'duration_mins'    => 18,
            'base_fare'        => 50.00,
            'total_fare'       => 180.00,
            'status'           => 'requested',
            'otp'              => '4821',
            'payment_method'   => 'upi',
        ]);

        // 1. Accept
        $acceptRes = $this->withHeaders($headers)->postJson("/api/rides/{$ride->ride_number}/accept");
        $acceptRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id'            => 'RIDE-8421',
                    'status'        => 'accepted',
                    'passengerName' => 'Aarav Sharma',
                ],
            ]);

        // 2. Arrive
        $arriveRes = $this->withHeaders($headers)->postJson("/api/rides/{$ride->ride_number}/arrive");
        $arriveRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id'          => 'RIDE-8421',
                    'status'      => 'arrived',
                    'requiresOtp' => true,
                ],
            ]);

        // 3. Start Trip (Verify PIN)
        $startRes = $this->withHeaders($headers)->postJson("/api/rides/{$ride->ride_number}/start", [
            'startPin' => '4821',
            'startLat' => 9.9252,
            'startLng' => 78.1198,
        ]);
        $startRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id'       => 'RIDE-8421',
                    'status'   => 'inTrip',
                    'baseFare' => 50.0,
                ],
            ]);

        // 4. Reach Destination
        $reachRes = $this->withHeaders($headers)->postJson("/api/rides/{$ride->ride_number}/reach-destination");
        $reachRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id'     => 'RIDE-8421',
                    'status' => 'reachedDestination',
                ],
            ]);

        // 5. Complete Trip
        $completeRes = $this->withHeaders($headers)->postJson("/api/rides/{$ride->ride_number}/complete", [
            'finalDistanceKm' => 6.4,
            'tollCharges'     => 0.0,
            'parkingCharges'  => 0.0,
            'cashCollected'   => false,
        ]);
        $completeRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id'             => 'RIDE-8421',
                    'status'         => 'completed',
                    'paymentMethod'  => 'UPI',
                    'paymentStatus'  => 'Paid',
                    'walletCredited' => true,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'fareBreakup' => [
                        'baseFare',
                        'distanceFare',
                        'timeFare',
                        'tolls',
                        'platformCommission',
                        'netDriverEarnings',
                        'totalFare',
                    ],
                ],
            ]);

        // 6. Ride Details
        $showRes = $this->withHeaders($headers)->getJson("/api/rides/{$ride->ride_number}");
        $showRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id'     => 'RIDE-8421',
                    'status' => 'completed',
                ],
            ]);

        // 7. Trip History
        $histRes = $this->withHeaders($headers)->getJson('/api/rides/history?page=1&limit=20');
        $histRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'page'  => 1,
                    'limit' => 20,
                ],
            ]);
    }

    public function test_cancel_ride_and_reject_ride(): void
    {
        $setup = $this->createAuthenticatedDriver();
        $headers = ['Authorization' => 'Bearer ' . $setup['accessToken']];

        $customerUser = User::create(['phone' => '91' . rand(10000000, 99999999), 'role' => User::ROLE_CUSTOMER, 'password' => bcrypt('123')]);
        $customer = Customer::create(['user_id' => $customerUser->id, 'phone' => $customerUser->phone]);

        // Reject ride
        $ride1 = Ride::create([
            'ride_number'     => 'RIDE-REJECT-1',
            'customer_id'     => $customer->id,
            'pickup_address'  => 'A',
            'pickup_lat'      => 9.9,
            'pickup_lng'      => 78.1,
            'dropoff_address' => 'B',
            'dropoff_lat'     => 9.9,
            'dropoff_lng'     => 78.1,
            'status'          => 'requested',
        ]);
        $rejectRes = $this->withHeaders($headers)->postJson("/api/rides/{$ride1->id}/reject", ['reason' => 'TOO_FAR']);
        $rejectRes->assertStatus(200)->assertJson(['success' => true, 'message' => 'Ride rejected.']);

        // Cancel active trip
        $ride2 = Ride::create([
            'ride_number'     => 'RIDE-CANCEL-1',
            'driver_id'       => $setup['driver']->id,
            'customer_id'     => $customer->id,
            'pickup_address'  => 'A',
            'pickup_lat'      => 9.9,
            'pickup_lng'      => 78.1,
            'dropoff_address' => 'B',
            'dropoff_lat'     => 9.9,
            'dropoff_lng'     => 78.1,
            'status'          => 'in_progress',
        ]);
        $cancelRes = $this->withHeaders($headers)->postJson("/api/rides/{$ride2->id}/cancel", [
            'reason'   => 'RIDER_NO_SHOW',
            'comments' => 'Rider did not answer calls for 10 minutes.',
        ]);
        $cancelRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status'                  => 'cancelled',
                    'cancellationFeeEligible' => true,
                    'cancellationFee'         => 50.0,
                ],
            ]);
    }

    public function test_earnings_and_wallet_and_bank_details(): void
    {
        $setup = $this->createAuthenticatedDriver();
        $headers = ['Authorization' => 'Bearer ' . $setup['accessToken']];

        // 1. Earnings Summary
        $earnRes = $this->withHeaders($headers)->getJson('/api/driver/earnings/summary?period=today');
        $earnRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'period' => 'today',
                ],
            ])
            ->assertJsonStructure([
                'data' => ['totalEarnings', 'totalTrips', 'onlineTime', 'chartPoints', 'incentives'],
            ]);

        // 2. Wallet
        $walletRes = $this->withHeaders($headers)->getJson('/api/driver/wallet');
        $walletRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'currency' => 'INR',
                ],
            ])
            ->assertJsonStructure([
                'data' => ['balance', 'pendingSettlement', 'transactions'],
            ]);

        // 3. Withdraw
        $withdrawRes = $this->withHeaders($headers)->postJson('/api/driver/wallet/withdraw', [
            'amount'        => 1000.0,
            'bankAccountId' => 'bank_99182',
        ]);
        $withdrawRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'statusCode' => 201,
                'message' => 'Withdrawal request submitted.',
                'data' => [
                    'amount' => 1000.0,
                    'status' => 'pending',
                ],
            ]);

        // 4. Update Bank Details
        $bankRes = $this->withHeaders($headers)->putJson('/api/driver/bank-details', [
            'bankName'          => 'HDFC Bank',
            'accountHolderName' => 'Rajesh Kumar',
            'accountNumber'     => '50100293819201',
            'ifscCode'          => 'HDFC0001234',
        ]);
        $bankRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Bank details updated successfully.',
                'data' => [
                    'bankName'   => 'HDFC Bank',
                    'ifscCode'   => 'HDFC0001234',
                    'isVerified' => true,
                ],
            ]);
    }

    public function test_notifications_and_settings_and_support_sos(): void
    {
        $setup = $this->createAuthenticatedDriver();
        $headers = ['Authorization' => 'Bearer ' . $setup['accessToken']];

        // 1. Notifications
        $notifRes = $this->withHeaders($headers)->getJson('/api/driver/notifications');
        $notifRes->assertStatus(200)->assertJson(['success' => true]);

        $markRes = $this->withHeaders($headers)->patchJson('/api/driver/notifications/1/read');
        $markRes->assertStatus(200);

        $clearRes = $this->withHeaders($headers)->deleteJson('/api/driver/notifications');
        $clearRes->assertStatus(200);

        // 2. Settings
        $getSetRes = $this->withHeaders($headers)->getJson('/api/driver/settings');
        $getSetRes->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['notificationsEnabled', 'language', 'themeMode', 'autoAcceptRides', 'navigationApp'],
            ]);

        $putSetRes = $this->withHeaders($headers)->putJson('/api/driver/settings', [
            'notificationsEnabled' => true,
            'language'             => 'English',
            'themeMode'            => 'dark',
            'autoAcceptRides'      => false,
            'navigationApp'        => 'google_maps',
        ]);
        $putSetRes->assertStatus(200)->assertJson(['data' => ['themeMode' => 'dark']]);

        // 3. Support Ticket
        $ticketRes = $this->withHeaders($headers)->postJson('/api/support/tickets', [
            'category'    => 'Fare Discrepancy',
            'subject'     => 'Fare for trip RIDE-8421 was incorrect',
            'description' => 'The toll charge of 50 was not included in the final bill.',
            'rideId'      => 'RIDE-8421',
        ]);
        $ticketRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'statusCode' => 201,
                'data' => [
                    'status' => 'OPEN',
                ],
            ]);

        // 4. Safety SOS
        $sosRes = $this->withHeaders($headers)->postJson('/api/driver/safety/sos', [
            'currentLat'   => 9.9252,
            'currentLng'   => 78.1198,
            'activeRideId' => 'RIDE-8421',
            'batteryLevel' => 84,
        ]);
        $sosRes->assertStatus(200)
            ->assertJson([
                'success'         => true,
                'statusCode'      => 200,
                'message'         => 'Emergency SOS triggered. Safety dispatch alerted.',
                'dispatchContact' => '+91112',
            ]);
    }

    public function test_standard_error_handling_structure(): void
    {
        $setup = $this->createAuthenticatedDriver();
        $headers = ['Authorization' => 'Bearer ' . $setup['accessToken']];

        // Trigger validation error on personal document upload
        $errRes = $this->withHeaders($headers)->postJson('/api/driver/documents/personal', [
            // missing required documentType, documentNumber, expiryDate
        ]);

        $errRes->assertStatus(400)
            ->assertJson([
                'success'    => false,
                'statusCode' => 400,
                'error'      => 'BAD_REQUEST',
            ])
            ->assertJsonStructure([
                'success',
                'statusCode',
                'error',
                'message',
                'errors' => [
                    [
                        'field',
                        'rule',
                        'message',
                    ],
                ],
            ]);
    }

    public function test_unaccepted_rides_list(): void
    {
        $setup = $this->createAuthenticatedDriver();
        $headers = ['Authorization' => 'Bearer ' . $setup['accessToken']];

        // Create a customer
        $customerUser = User::create([
            'name'     => 'Ananya Verma',
            'phone'    => '919876543299',
            'email'    => 'ananya@example.com',
            'role'     => User::ROLE_CUSTOMER,
            'password' => bcrypt('password123'),
        ]);
        $customer = Customer::create([
            'user_id'       => $customerUser->id,
            'customer_code' => 'CUST-88991',
            'name'          => 'Ananya Verma',
            'phone'         => '919876543299',
            'email'         => 'ananya@example.com',
            'rating'        => 4.9,
        ]);

        // 1. Create an unaccepted / requested ride (Bangalore coords)
        $unacceptedRide = Ride::create([
            'customer_id'     => $customer->id,
            'vehicle_type'    => 'sedan',
            'ride_category'   => 'daily',
            'pickup_address'  => 'Indiranagar 100ft Rd, Bangalore',
            'pickup_lat'      => 12.9784,
            'pickup_lng'      => 77.6408,
            'dropoff_address' => 'Koramangala 5th Block, Bangalore',
            'dropoff_lat'     => 12.9352,
            'dropoff_lng'     => 77.6245,
            'distance_km'     => 5.8,
            'duration_mins'   => 18,
            'base_fare'       => 50.00,
            'distance_fare'   => 87.00,
            'time_fare'       => 36.00,
            'total_fare'      => 173.00,
            'payment_method'  => 'cash',
            'payment_status'  => 'pending',
            'status'          => 'requested',
            'otp'             => '4582',
        ]);

        // 2. Create an already accepted/ongoing ride (should NOT be returned)
        Ride::create([
            'customer_id'     => $customer->id,
            'driver_id'       => $setup['driver']->id,
            'vehicle_type'    => 'sedan',
            'ride_category'   => 'daily',
            'pickup_address'  => 'MG Road, Bangalore',
            'pickup_lat'      => 12.9750,
            'pickup_lng'      => 77.6090,
            'dropoff_address' => 'Whitefield, Bangalore',
            'dropoff_lat'     => 12.9698,
            'dropoff_lng'     => 77.7499,
            'distance_km'     => 16.0,
            'total_fare'      => 450.00,
            'status'          => 'in_progress',
        ]);

        // Test GET /api/rides/unaccepted with coordinates
        $res = $this->withHeaders($headers)->getJson('/api/rides/unaccepted?lat=12.9716&lng=77.5946&radius=20');
        $res->assertStatus(200)
            ->assertJson([
                'success'    => true,
                'statusCode' => 200,
                'message'    => 'Available rides retrieved successfully.',
            ])
            ->assertJsonStructure([
                'success',
                'statusCode',
                'message',
                'data' => [
                    [
                        'id',
                        'rideId',
                        'rideNumber',
                        'status',
                        'passengerName',
                        'pickupLocation',
                        'destinationLocation',
                        'pickupLat',
                        'pickupLng',
                        'destinationLat',
                        'destinationLng',
                        'tripDistance',
                        'tripDuration',
                        'distanceToPickup',
                        'distanceToPickupKm',
                        'pickupEta',
                        'fare',
                        'estimatedFare',
                        'vehicleType',
                        'rideCategory',
                        'paymentMethod',
                    ]
                ],
                'meta' => [
                    'total',
                    'page',
                    'limit',
                ]
            ]);

        $rides = $res->json('data');
        $this->assertNotEmpty($rides);
        $found = collect($rides)->firstWhere('rideId', $unacceptedRide->id);
        $this->assertNotNull($found);
        $this->assertEquals('Ananya Verma', $found['passengerName']);
        $this->assertEquals('sedan', $found['vehicleType']);
        $this->assertEquals('requested', $found['status']);
        $this->assertNotNull($found['distanceToPickupKm']);

        // Test alias GET /api/driver/rides/requests
        $aliasRes = $this->withHeaders($headers)->getJson('/api/driver/rides/requests?vehicle_type=sedan');
        $aliasRes->assertStatus(200)
            ->assertJson(['success' => true]);

        // Test vehicle_type filter excludes mismatch
        $mismatchRes = $this->withHeaders($headers)->getJson('/api/driver/rides/unaccepted?vehicle_type=bike');
        $mismatchRes->assertStatus(200);
        $mismatchData = $mismatchRes->json('data');
        $this->assertEmpty(collect($mismatchData)->where('rideId', $unacceptedRide->id));
    }
}

