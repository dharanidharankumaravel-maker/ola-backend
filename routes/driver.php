<?php

use App\Http\Controllers\Driver\AuthController;
use App\Http\Controllers\Driver\EarningsController;
use App\Http\Controllers\Driver\NotificationController;
use App\Http\Controllers\Driver\ProfileController;
use App\Http\Controllers\Driver\RideController;
use App\Http\Controllers\Driver\SettingController;
use App\Http\Controllers\Driver\SupportController;
use App\Http\Controllers\Driver\TelemetryController;
use App\Http\Controllers\Driver\VehicleController;
use App\Http\Controllers\Driver\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Driver API Routes
|--------------------------------------------------------------------------
|
| Base URL prefix from bootstrap/app.php is '/api'
|
*/

// ==========================================
// 4.1 Authentication & Onboarding (Public)
// ==========================================
$registerAuth = function () {
    Route::post('send-otp', [AuthController::class, 'sendOtp']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('refresh-token', [AuthController::class, 'refreshToken']);
};

Route::prefix('auth')->group($registerAuth);
Route::prefix('driver/auth')->group($registerAuth);

// Authenticated Driver Routes
Route::middleware('driver.auth')->group(function () {

    // 4.1 Logout
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('driver/auth/logout', [AuthController::class, 'logout']);

    // ==========================================
    // 4.2 Driver Profile & KYC Verification
    // ==========================================
    Route::prefix('driver')->group(function () {
        Route::post('profile', [ProfileController::class, 'storeOrUpdate']);
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);

        Route::post('documents/personal', [ProfileController::class, 'uploadPersonalDocument']);
        Route::post('documents/renew', [ProfileController::class, 'renewDocument']);

        // ==========================================
        // 4.3 Vehicle Management & Documentation
        // ==========================================
        Route::post('vehicle', [VehicleController::class, 'registerVehicle']);
        Route::post('vehicle/documents', [VehicleController::class, 'uploadVehicleDocument']);

        // ==========================================
        // 4.4 Driver Status & Telemetry
        // ==========================================
        Route::post('status/toggle', [TelemetryController::class, 'toggleStatus']);
        Route::post('telemetry/location', [TelemetryController::class, 'updateLocation']);

        // ==========================================
        // 4.6 Earnings & Performance Analytics
        // ==========================================
        Route::get('earnings/summary', [EarningsController::class, 'summary']);

        // ==========================================
        // 4.7 Wallet, Payouts & Banking
        // ==========================================
        Route::get('wallet', [WalletController::class, 'wallet']);
        Route::post('wallet/withdraw', [WalletController::class, 'withdraw']);
        Route::put('bank-details', [WalletController::class, 'updateBankDetails']);

        // ==========================================
        // 4.8 Alerts & In-App Notifications
        // ==========================================
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('notifications', [NotificationController::class, 'destroyAll']);

        // ==========================================
        // 4.9 Settings & Configuration
        // ==========================================
        Route::get('settings', [SettingController::class, 'show']);
        Route::put('settings', [SettingController::class, 'update']);

        // ==========================================
        // 4.10 Safety SOS
        // ==========================================
        Route::post('safety/sos', [SupportController::class, 'triggerSos']);

        // Support tickets alias
        Route::post('support/tickets', [SupportController::class, 'submitTicket']);
    });

    // ==========================================
    // 4.5 Ride & Dispatch Lifecycle
    // ==========================================
    $registerRideRoutes = function () {
        Route::get('unaccepted', [RideController::class, 'unacceptedRides']);
        Route::get('requests', [RideController::class, 'unacceptedRides']);
        Route::get('available', [RideController::class, 'unacceptedRides']);
        Route::get('history', [RideController::class, 'history']);
        Route::post('{id}/accept', [RideController::class, 'accept']);
        Route::post('{id}/reject', [RideController::class, 'reject']);
        Route::post('{id}/arrive', [RideController::class, 'arrive']);
        Route::post('{id}/start', [RideController::class, 'start']);
        Route::post('{id}/reach-destination', [RideController::class, 'reachDestination']);
        Route::post('{id}/complete', [RideController::class, 'complete']);
        Route::post('{id}/cancel', [RideController::class, 'cancel']);
        Route::get('{id}', [RideController::class, 'show'])->where('id', '^(?!current$|scheduled$|estimates$|unaccepted$|requests$|available$).+');
    };

    Route::prefix('rides')->group($registerRideRoutes);
    Route::prefix('driver/rides')->group($registerRideRoutes);

    // ==========================================
    // 4.10 Help & Support Tickets
    // ==========================================
    Route::post('support/tickets', [SupportController::class, 'submitTicket']);
});
