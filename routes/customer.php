<?php

use App\Http\Controllers\Customer\AuthController;
use App\Http\Controllers\Customer\CustomerSupportController;
use App\Http\Controllers\Customer\FareEstimateController;
use App\Http\Controllers\Customer\MapController;
use App\Http\Controllers\Customer\NearbyDriverController;
use App\Http\Controllers\Customer\OfferController;
use App\Http\Controllers\Customer\ParcelController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\RideController;
use App\Http\Controllers\Customer\RideLocationController;
use App\Http\Controllers\Customer\UserProfileController;
use App\Http\Controllers\Customer\VehicleTypeController;
use App\Http\Controllers\Customer\WalletController;
use App\Http\Middleware\Customer\CustomerAuthenticate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer API Routes
|--------------------------------------------------------------------------
|
| Base URL prefix: /api  (from bootstrap/app.php)
|
| Module 1:  Authentication          → /api/auth/*  or /api/customer/auth/*
| Module 2:  User Profile            → /api/users/*
| Module 3:  Map & Places            → /api/map/*   /api/users/saved-places*
| Module 4:  Nearby Drivers          → /api/drivers/nearby
| Module 5:  Fare Estimates          → /api/rides/estimates
| Module 6:  Ride Lifecycle          → /api/rides/*
| Module 7:  Alo Parcel              → /api/parcel/*
| Module 8:  Alo Money / Wallet      → /api/wallet/*  /api/rides/:id/pay
| Module 9:  Ratings & Feedback      → /api/rides/:id/feedback
| Module 10: Safety SOS              → /api/safety/*  /api/rides/:id/share
| Module 11: Support & FAQs          → /api/support/*
| Module 12: Device Registration     → /api/notifications/device
|
*/

// ============================================================
// MODULE 1: Authentication (Public)
// ============================================================
$registerAuthRoutes = function () {
    // 1.1 Send OTP
    Route::post('otp/send', [AuthController::class, 'sendOtp']);

    // 1.2 Verify OTP & Login
    Route::post('otp/verify', [AuthController::class, 'verifyOtp']);

    // 1.3 Refresh Auth Token
    Route::post('refresh', [AuthController::class, 'refresh']);

    // ── Authenticated routes under /auth prefix ──────────────────
    Route::middleware(CustomerAuthenticate::class)->group(function () {

        // 1.4 Get Current User Profile
        Route::get('me', [AuthController::class, 'me']);

        // 1.5 Logout
        Route::post('logout', [AuthController::class, 'logout']);

        // Legacy profile update (kept for backward compatibility)
        Route::match(['put', 'patch'], 'profile', [ProfileController::class, 'updateProfile']);

        // Legacy ride location APIs
        Route::post('rides/current-location', [RideLocationController::class, 'storeCurrentLocation']);
        Route::post('rides/destination',       [RideLocationController::class, 'updateDestination']);
        Route::get('rides/{ride_id}',          [RideLocationController::class, 'getRideDetails']);

        // Legacy offer / vehicle APIs
        Route::get('offers',          [OfferController::class, 'getAvailableOffers']);
        Route::post('offers/validate', [OfferController::class, 'validatePromoCode']);
        Route::get('vehicle-types',   [VehicleTypeController::class, 'index']);
    });
};

// Route group 1: /api/customer/auth/*
Route::prefix('customer')->group(function () use ($registerAuthRoutes) {
    Route::prefix('auth')->group($registerAuthRoutes);
});

// Route group 2: /api/auth/* (direct alias)
Route::prefix('auth')->group($registerAuthRoutes);

// ============================================================
// All remaining modules require authentication
// ============================================================
Route::middleware(CustomerAuthenticate::class)->group(function () {

    // ──────────────────────────────────────────────────────────────────────
    // MODULE 2: User Profile & Account Settings
    // ──────────────────────────────────────────────────────────────────────
    Route::prefix('users')->group(function () {
        Route::get('profile',                             [UserProfileController::class, 'getProfile']);
        Route::match(['put', 'patch', 'post'], 'profile', [UserProfileController::class, 'updateProfile']);
        Route::patch('preferences',      [UserProfileController::class, 'updatePreferences']);
        Route::put('emergency-contact',  [UserProfileController::class, 'setEmergencyContact']);
        Route::delete('account',         [UserProfileController::class, 'deleteAccount']);

        // MODULE 3.4: Saved Places
        Route::get('saved-places',          [MapController::class, 'getSavedPlaces']);
        Route::post('saved-places',         [MapController::class, 'addSavedPlace']);
        Route::delete('saved-places/{id}',  [MapController::class, 'deleteSavedPlace']);
    });

    // ──────────────────────────────────────────────────────────────────────
    // MODULE 3: Map, Places & Locations  (some routes are public)
    // ──────────────────────────────────────────────────────────────────────
    // Public routes defined outside middleware below

    // ──────────────────────────────────────────────────────────────────────
    // MODULE 4: Real-time Ghost Cars & Nearby Drivers
    // ──────────────────────────────────────────────────────────────────────
    Route::get('drivers/nearby', [NearbyDriverController::class, 'nearby']);

    // ──────────────────────────────────────────────────────────────────────
    // MODULE 5: Fare Estimates
    // ──────────────────────────────────────────────────────────────────────
    Route::post('rides/estimates', [FareEstimateController::class, 'calculate']);

    // ──────────────────────────────────────────────────────────────────────
    // MODULE 5.2: Validate Coupon (existing OfferController)
    // ──────────────────────────────────────────────────────────────────────
    Route::post('promos/validate', [OfferController::class, 'validatePromoCode']);

    // ──────────────────────────────────────────────────────────────────────
    // MODULE 6: Ride Lifecycle & Booking
    // ──────────────────────────────────────────────────────────────────────
    Route::post('rides/book',                [RideController::class, 'book']);
    Route::get('rides/current',              [RideController::class, 'current']);
    Route::get('rides/history',              [RideController::class, 'history']);
    Route::get('rides/scheduled',            [RideController::class, 'scheduledRides']);
    Route::delete('rides/scheduled/{id}',    [RideController::class, 'cancelScheduled']);
    Route::get('rides/{id}',                 [RideController::class, 'show'])->where('id', '^(?!current$|scheduled$|estimates$|unaccepted$|requests$|available$).+');
    Route::patch('rides/{id}/tip',           [RideController::class, 'updateTip']);
    Route::post('rides/{id}/cancel',         [RideController::class, 'cancel']);

    // ──────────────────────────────────────────────────────────────────────
    // MODULE 7: Alo Parcel
    // ──────────────────────────────────────────────────────────────────────
    Route::post('parcel/book',    [ParcelController::class, 'book']);
    Route::post('parcel/upload',  [ParcelController::class, 'upload']);

    // ──────────────────────────────────────────────────────────────────────
    // MODULE 8: Alo Money (Wallet) & Payments
    // ──────────────────────────────────────────────────────────────────────
    Route::get('wallet',              [WalletController::class, 'wallet']);
    Route::post('wallet/topup',       [WalletController::class, 'topup']);
    Route::post('rides/{id}/pay',     [WalletController::class, 'pay']);

    // ──────────────────────────────────────────────────────────────────────
    // MODULE 9: Ratings & Feedback
    // ──────────────────────────────────────────────────────────────────────
    Route::post('rides/{id}/feedback', [CustomerSupportController::class, 'submitFeedback']);

    // ──────────────────────────────────────────────────────────────────────
    // MODULE 10: Safety & Emergency (SOS)
    // ──────────────────────────────────────────────────────────────────────
    Route::post('safety/sos',          [CustomerSupportController::class, 'triggerSos']);
    Route::post('rides/{id}/share',    [CustomerSupportController::class, 'shareRide']);

    // ──────────────────────────────────────────────────────────────────────
    // MODULE 11: Support & FAQs
    // ──────────────────────────────────────────────────────────────────────
    Route::post('support/tickets',     [CustomerSupportController::class, 'createTicket']);

    // ──────────────────────────────────────────────────────────────────────
    // MODULE 12: Push Notification Device Registration
    // ──────────────────────────────────────────────────────────────────────
    Route::post('notifications/device', [CustomerSupportController::class, 'registerDevice']);
});

// ============================================================
// MODULE 3 Public Map Routes (Auth Optional)
// ============================================================
Route::get('map/search',         [MapController::class, 'search']);
Route::get('map/reverse-geocode',[MapController::class, 'reverseGeocode']);
Route::post('map/route',         [MapController::class, 'route']);

// MODULE 11 FAQs (Auth Optional)
Route::get('support/faqs', [CustomerSupportController::class, 'getFaqs']);
