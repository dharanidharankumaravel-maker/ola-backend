<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Customer\CustomerAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerAppModulesTest extends TestCase
{
    use DatabaseTransactions;

    protected Customer $customer;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'phone' => '9988776655',
            'role' => 'customer',
            'status' => 'active',
            'password' => Hash::make('password123'),
        ]);

        $this->customer = Customer::create([
            'user_id' => $this->user->id,
            'customer_code' => 'CUST-99999',
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'phone' => '9988776655',
            'avatar' => 'Felix',
            'rating' => 5.0,
            'wallet_balance' => 500.00,
            'total_rides' => 5,
            'total_spent' => 1500.00,
            'status' => 'active',
        ]);

        $authService = app(CustomerAuthService::class);
        $tokens = $authService->generateTokens($this->customer);
        $this->token = $tokens['accessToken'];
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    /** Module 2: Profile & Account Settings */
    public function test_module_2_profile_and_preferences(): void
    {
        // 2.1 Update profile
        $res = $this->putJson('/api/users/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'avatar_id' => 'avatar_02',
        ], $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('customers', ['id' => $this->customer->id, 'name' => 'Updated Name']);

        // 2.2 Update preferences
        $res = $this->patchJson('/api/users/preferences', [
            'push_notifications' => true,
            'sms_updates' => false,
            'promo_emails' => true,
            'precise_location' => true,
        ], $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);

        // 2.3 Set emergency contact
        $res = $this->putJson('/api/users/emergency-contact', [
            'name' => 'Jane Mom',
            'phone' => '+919876543211',
            'relationship' => 'Mother',
        ], $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);
        $this->customer->refresh();
        $this->assertEquals('+919876543211', $this->customer->emergency_contact);

        // Verify GET /users/profile returns emergencyContact
        $profileRes = $this->getJson('/api/users/profile', $this->authHeaders());
        $profileRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'emergencyContact' => '+919876543211',
                    'emergency_contact' => '+919876543211',
                ],
            ]);

        // Verify GET /auth/me returns emergencyContact
        $meRes = $this->getJson('/api/auth/me', $this->authHeaders());
        $meRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'emergencyContact' => '+919876543211',
                ],
            ]);
    }

    /** Module 3: Map, Places & Saved Locations */
    public function test_module_3_map_and_saved_places(): void
    {
        // 3.1 Search places (public)
        $res = $this->getJson('/api/map/search?q=Marina+Beach');
        $res->assertStatus(200)->assertJson(['success' => true]);

        // 3.2 Reverse geocode
        $res = $this->getJson('/api/map/reverse-geocode?lat=13.0827&lng=80.2707');
        $res->assertStatus(200)->assertJson(['success' => true]);

        // 3.3 Route calculation
        $res = $this->postJson('/api/map/route', [
            'origin' => ['lat' => 13.0827, 'lng' => 80.2707],
            'destination' => ['lat' => 12.9815, 'lng' => 80.2180],
        ]);
        $res->assertStatus(200)->assertJson(['success' => true]);

        // 3.4 Saved places CRUD
        $res = $this->postJson('/api/users/saved-places', [
            'label' => 'Home',
            'address' => '123 Test Street, Chennai',
            'lat' => 13.0827,
            'lng' => 80.2707,
        ], $this->authHeaders());
        $res->assertStatus(201)->assertJson(['success' => true]);
        $placeId = $res->json('data.id');

        $res = $this->getJson('/api/users/saved-places', $this->authHeaders());
        $res->assertStatus(200)->assertJsonCount(1, 'data');

        $res = $this->deleteJson('/api/users/saved-places/' . $placeId, [], $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);
    }

    /** Module 4: Nearby Drivers */
    public function test_module_4_nearby_drivers(): void
    {
        $res = $this->getJson('/api/drivers/nearby?lat=13.0827&lng=80.2707&category=daily', $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);
    }

    /** Module 5: Fare Estimates */
    public function test_module_5_fare_estimates(): void
    {
        $res = $this->postJson('/api/rides/estimates', [
            'pickup' => ['lat' => 13.0827, 'lng' => 80.2707, 'address' => 'Central'],
            'drop' => ['lat' => 12.9815, 'lng' => 80.2180, 'address' => 'Airport'],
            'ride_category' => 'daily',
        ], $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);
        $this->assertNotEmpty($res->json('data.estimates'));
    }

    /** Module 6: Ride Lifecycle & Management */
    public function test_module_6_ride_lifecycle(): void
    {
        // 6.1 Book a ride
        $res = $this->postJson('/api/rides/book', [
            'vehicle_type' => 'sedan',
            'pickup' => ['lat' => 13.0827, 'lng' => 80.2707, 'address' => 'Central Station'],
            'drop' => ['lat' => 12.9815, 'lng' => 80.2180, 'address' => 'Airport Terminal 1'],
            'payment_method' => 'wallet',
        ], $this->authHeaders());
        $res->assertStatus(201)->assertJson(['success' => true]);
        $rideId = $res->json('data.id');

        // 6.2 Current active ride
        $res = $this->getJson('/api/rides/current', $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);

        // 6.3 Update tip
        $res = $this->patchJson('/api/rides/' . $rideId . '/tip', [
            'tip_amount' => 50.00,
        ], $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);

        // 6.4 Show ride details
        $res = $this->getJson('/api/rides/' . $rideId, $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);

        // 6.5 Cancel ride
        $res = $this->postJson('/api/rides/' . $rideId . '/cancel', [
            'reason' => 'Changed plans',
        ], $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);

        // 6.6 Ride history
        $res = $this->getJson('/api/rides/history', $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);
    }

    /** Module 7: Parcel Delivery */
    public function test_module_7_parcel_delivery(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('parcel.jpg');
        $res = $this->postJson('/api/parcel/upload', [
            'photo' => $file,
        ], $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);
        $photoUrl = $res->json('data.photo_url');

        $res = $this->postJson('/api/parcel/book', [
            'pickup' => ['lat' => 13.0827, 'lng' => 80.2707, 'address' => 'Office'],
            'drop' => ['lat' => 12.9815, 'lng' => 80.2180, 'address' => 'Client Location'],
            'weight_kg' => 2.5,
            'package_type' => 'documents',
            'receiver_name' => 'Bob Smith',
            'receiver_phone' => '+919123456789',
            'instructions' => 'Call upon arrival',
            'photo_url' => $photoUrl,
            'payment_method' => 'wallet',
        ], $this->authHeaders());
        $res->assertStatus(201)->assertJson(['success' => true]);
        $this->assertEquals('parcel', $res->json('data.ride_category'));
    }

    /** Module 8: Wallet & Payments */
    public function test_module_8_wallet_and_payments(): void
    {
        // 8.1 Get wallet balance & passbook
        $res = $this->getJson('/api/wallet', $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);

        // 8.2 Top-up wallet
        $res = $this->postJson('/api/wallet/topup', [
            'amount' => 200.00,
            'payment_gateway' => 'razorpay',
            'transaction_ref' => 'TXN-TEST-1234',
        ], $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);
        $this->customer->refresh();
        $this->assertEquals(700.00, (float) $this->customer->wallet_balance);
    }

    /** Module 9: Ratings & Feedback */
    public function test_module_9_ratings_and_feedback(): void
    {
        $ride = Ride::create([
            'customer_id' => $this->customer->id,
            'ride_number' => 'RIDE-TEST01',
            'pickup_address' => 'Point A',
            'pickup_lat' => 13.0827,
            'pickup_lng' => 80.2707,
            'dropoff_address' => 'Point B',
            'dropoff_lat' => 12.9815,
            'dropoff_lng' => 80.2180,
            'vehicle_type' => 'sedan',
            'total_fare' => 250.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        $res = $this->postJson('/api/rides/' . $ride->id . '/feedback', [
            'rating' => 5,
            'tags' => ['Clean car', 'Polite driver'],
            'comments' => 'Excellent service!',
            'tip_amount' => 20.00,
        ], $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);
        $ride->refresh();
        $this->assertEquals(5, $ride->driver_rating);
    }

    /** Module 10: Safety & SOS */
    public function test_module_10_safety_and_sos(): void
    {
        $ride = Ride::create([
            'customer_id' => $this->customer->id,
            'ride_number' => 'RIDE-TEST02',
            'pickup_address' => 'Point A',
            'pickup_lat' => 13.0827,
            'pickup_lng' => 80.2707,
            'dropoff_address' => 'Point B',
            'dropoff_lat' => 12.9815,
            'dropoff_lng' => 80.2180,
            'vehicle_type' => 'sedan',
            'total_fare' => 100.00,
            'status' => 'in_progress',
        ]);

        // 10.1 Trigger SOS
        $res = $this->postJson('/api/safety/sos', [
            'ride_id' => $ride->id,
            'lat' => 13.0827,
            'lng' => 80.2707,
        ], $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);

        // 10.2 Share ride live tracking
        $res = $this->postJson('/api/rides/' . $ride->id . '/share', [
            'recipients' => ['+919876543210'],
        ], $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);
        $this->assertNotEmpty($res->json('data.tracking_url'));
    }

    /** Module 11: Support & FAQs */
    public function test_module_11_support_and_faqs(): void
    {
        // 11.1 FAQs (public)
        $res = $this->getJson('/api/support/faqs');
        $res->assertStatus(200)->assertJson(['success' => true]);

        // 11.2 Create support ticket
        $res = $this->postJson('/api/support/tickets', [
            'subject' => 'Incorrect fare deduction',
            'description' => 'I was charged twice for ride #1234',
            'category' => 'fare_issue',
        ], $this->authHeaders());
        $res->assertStatus(201)->assertJson(['success' => true]);
    }

    /** Module 12: Push Notification Device Registration */
    public function test_module_12_device_registration(): void
    {
        $res = $this->postJson('/api/notifications/device', [
            'fcm_token' => 'sample_fcm_token_device_xyz',
            'platform' => 'android',
        ], $this->authHeaders());
        $res->assertStatus(200)->assertJson(['success' => true]);
    }
}
