<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use App\Models\SystemSetting;
use App\Models\CmsPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed initial essential admin credentials and system configuration.
     */
    public function run(): void
    {
        // 1. Admin Account (Roles are strictly: admin, customer, driver)
        User::updateOrCreate(
            ['email' => 'admin@alo.com'],
            [
                'name' => 'Alo Admin',
                'phone' => '+91 9876543210',
                'role' => 'admin',
                'status' => 'active',
                'password' => Hash::make('admin123'),
            ]
        );

        // 2. Sample Customer (Dharani)
        $customerUser = User::updateOrCreate(
            ['email' => 'dharani@example.com'],
            [
                'name' => 'Dharani',
                'phone' => '+919876543210',
                'role' => 'customer',
                'status' => 'active',
                'password' => Hash::make('secret123'),
            ]
        );

        Customer::updateOrCreate(
            ['phone' => '+919876543210'],
            [
                'user_id' => $customerUser->id,
                'customer_code' => 'CUST-00001',
                'name' => 'Dharani',
                'email' => 'dharani@example.com',
                'phone' => '+919876543210',
                'avatar' => 'Felix',
                'rating' => 4.80,
                'wallet_balance' => 250.00,
                'total_rides' => 14,
                'total_spent' => 1250.00,
                'referral_code' => 'VERON123',
                'status' => 'active',
                'created_at' => '2026-01-15 10:30:00',
            ]
        );

        // 2. Default System Settings
        $settings = [
            ['key' => 'app_name', 'value' => 'Alo Mobility', 'group' => 'general'],
            ['key' => 'currency_symbol', 'value' => '₹', 'group' => 'general'],
            ['key' => 'helpline_number', 'value' => '1800-256-4444', 'group' => 'general'],
            ['key' => 'support_email', 'value' => 'support@alo.com', 'group' => 'general'],
            ['key' => 'commission_percentage', 'value' => '15', 'group' => 'pricing'],
            ['key' => 'gst_percentage', 'value' => '5', 'group' => 'pricing'],
            
            // Base fare per vehicle type
            ['key' => 'fare_bike_base', 'value' => '25', 'group' => 'pricing'],
            ['key' => 'fare_bike_per_km', 'value' => '8', 'group' => 'pricing'],
            ['key' => 'fare_auto_base', 'value' => '35', 'group' => 'pricing'],
            ['key' => 'fare_auto_per_km', 'value' => '12', 'group' => 'pricing'],
            ['key' => 'fare_mini_base', 'value' => '50', 'group' => 'pricing'],
            ['key' => 'fare_mini_per_km', 'value' => '14', 'group' => 'pricing'],
            ['key' => 'fare_sedan_base', 'value' => '70', 'group' => 'pricing'],
            ['key' => 'fare_sedan_per_km', 'value' => '18', 'group' => 'pricing'],
            ['key' => 'fare_suv_base', 'value' => '100', 'group' => 'pricing'],
            ['key' => 'fare_suv_per_km', 'value' => '24', 'group' => 'pricing'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }

        // 3. Default CMS Pages
        $cms = [
            [
                'title' => 'Terms of Service',
                'slug' => 'terms-of-service',
                'content' => '<h2>1. Terms of Service</h2><p>Welcome to Alo Mobility. By using our platform, riders and drivers agree to these terms.</p>',
                'meta_title' => 'Terms of Service - Alo',
                'status' => 'published',
            ],
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'content' => '<h2>1. Privacy Policy</h2><p>Alo respects your privacy. We collect location data solely to facilitate ride booking and safety.</p>',
                'meta_title' => 'Privacy Policy - Alo',
                'status' => 'published',
            ],
            [
                'title' => 'Driver Agreement',
                'slug' => 'driver-agreement',
                'content' => '<h2>Driver Partner Agreement</h2><p>Terms and conditions for partner drivers operating on the Alo ride platform.</p>',
                'meta_title' => 'Driver Agreement - Alo',
                'status' => 'published',
            ],
            [
                'title' => 'Safety Guidelines',
                'slug' => 'safety-guidelines',
                'content' => '<h2>Safety Guidelines</h2><p>Our 24/7 Safety Response Team and live SOS monitoring keep every trip secure.</p>',
                'meta_title' => 'Safety Guidelines - Alo',
                'status' => 'published',
            ],
        ];

        foreach ($cms as $c) {
            CmsPage::firstOrCreate(['slug' => $c['slug']], $c);
        }
    }
}
