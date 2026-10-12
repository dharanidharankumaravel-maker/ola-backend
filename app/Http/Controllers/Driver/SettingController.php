<?php

namespace App\Http\Controllers\Driver;

use App\Models\DriverSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends BaseDriverController
{
    /**
     * Get Driver Settings
     * GET /driver/settings
     */
    public function show(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $settings = DriverSetting::firstOrCreate(
            ['driver_id' => $driver->id],
            [
                'notifications_enabled' => true,
                'language'              => 'English',
                'theme_mode'            => 'system',
                'auto_accept_rides'     => false,
                'navigation_app'        => 'google_maps',
            ]
        );

        return $this->successResponse([
            'notificationsEnabled' => (bool) $settings->notifications_enabled,
            'language'             => $settings->language,
            'themeMode'            => $settings->theme_mode,
            'autoAcceptRides'      => (bool) $settings->auto_accept_rides,
            'navigationApp'        => $settings->navigation_app,
        ]);
    }

    /**
     * Update Driver Settings
     * PUT /driver/settings
     */
    public function update(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $settings = DriverSetting::firstOrCreate(['driver_id' => $driver->id]);

        if ($request->has('notificationsEnabled')) {
            $settings->notifications_enabled = (bool) $request->input('notificationsEnabled');
        }
        if ($request->filled('language')) {
            $settings->language = (string) $request->input('language');
        }
        if ($request->filled('themeMode')) {
            $settings->theme_mode = (string) $request->input('themeMode');
        }
        if ($request->has('autoAcceptRides')) {
            $settings->auto_accept_rides = (bool) $request->input('autoAcceptRides');
        }
        if ($request->filled('navigationApp')) {
            $settings->navigation_app = (string) $request->input('navigationApp');
        }

        $settings->save();

        return $this->successResponse([
            'notificationsEnabled' => (bool) $settings->notifications_enabled,
            'language'             => $settings->language,
            'themeMode'            => $settings->theme_mode,
            'autoAcceptRides'      => (bool) $settings->auto_accept_rides,
            'navigationApp'        => $settings->navigation_app,
        ]);
    }
}
