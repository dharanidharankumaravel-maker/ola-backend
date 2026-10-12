<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RideController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\FinancialController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\CmsController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\AuthController;

// Root redirect
Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('admin.login');
Route::post('/login', [AuthController::class, 'login'])->name('admin.login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

// Protected Admin Routes
Route::middleware(['auth'])->name('admin.')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Operations: Rides
    Route::prefix('rides')->name('rides.')->group(function () {
        Route::get('/', [RideController::class, 'index'])->name('index');
        Route::get('/{id}', [RideController::class, 'show'])->name('show');
        Route::post('/{id}/status', [RideController::class, 'updateStatus'])->name('status');
    });

    // Management: Drivers
    Route::prefix('drivers')->name('drivers.')->group(function () {
        Route::get('/', [DriverController::class, 'index'])->name('index');
        Route::get('/{id}', [DriverController::class, 'show'])->name('show');
        Route::put('/{id}', [DriverController::class, 'update'])->name('update');
        Route::post('/{id}/approve', [DriverController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [DriverController::class, 'reject'])->name('reject');
        Route::post('/{id}/toggle-block', [DriverController::class, 'toggleBlock'])->name('toggle-block');
        Route::delete('/{id}', [DriverController::class, 'destroy'])->name('destroy');
    });

    // Management: Customers
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/{id}', [CustomerController::class, 'show'])->name('show');
        Route::post('/{id}/toggle-block', [CustomerController::class, 'toggleBlock'])->name('toggle-block');
        Route::post('/{id}/adjust-wallet', [CustomerController::class, 'adjustWallet'])->name('adjust-wallet');
    });

    // Management: Vehicles
    Route::prefix('vehicles')->name('vehicles.')->group(function () {
        Route::get('/', [VehicleController::class, 'index'])->name('index');
        Route::put('/{id}', [VehicleController::class, 'update'])->name('update');
        Route::post('/{id}/status', [VehicleController::class, 'updateStatus'])->name('status');
        Route::delete('/{id}', [VehicleController::class, 'destroy'])->name('destroy');
    });

    // Management: Promotions
    Route::prefix('promotions')->name('promotions.')->group(function () {
        Route::get('/', [PromotionController::class, 'index'])->name('index');
        Route::post('/', [PromotionController::class, 'store'])->name('store');
        Route::put('/{id}', [PromotionController::class, 'update'])->name('update');
        Route::post('/{id}/toggle', [PromotionController::class, 'toggleStatus'])->name('toggle');
        Route::delete('/{id}', [PromotionController::class, 'destroy'])->name('destroy');
    });

    // Financials & Analytics
    Route::prefix('financials')->name('financials.')->group(function () {
        Route::get('/', [FinancialController::class, 'index'])->name('index');
        Route::post('/payouts/{id}/process', [FinancialController::class, 'processPayout'])->name('payouts.process');
    });

    // System: Complaints & Tickets
    Route::prefix('complaints')->name('complaints.')->group(function () {
        Route::get('/', [ComplaintController::class, 'index'])->name('index');
        Route::get('/{id}', [ComplaintController::class, 'show'])->name('show');
        Route::post('/{id}/reply', [ComplaintController::class, 'reply'])->name('reply');
        Route::post('/{id}/status', [ComplaintController::class, 'updateStatus'])->name('status');
    });

    // System: CMS
    Route::prefix('cms')->name('cms.')->group(function () {
        Route::get('/', [CmsController::class, 'index'])->name('index');
        Route::get('/create', [CmsController::class, 'create'])->name('create');
        Route::post('/', [CmsController::class, 'store'])->name('store');
        Route::get('/{slug}', [CmsController::class, 'edit'])->name('edit');
        Route::put('/{slug}', [CmsController::class, 'update'])->name('update');
    });

    // System: Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('index');
        Route::post('/update', [SettingController::class, 'update'])->name('update');
    });

    // System: Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/send', [NotificationController::class, 'send'])->name('send');
    });

    // Profile & Account
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'index'])->name('index');
        Route::put('/update', [ProfileController::class, 'update'])->name('update');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password');
    });
});
