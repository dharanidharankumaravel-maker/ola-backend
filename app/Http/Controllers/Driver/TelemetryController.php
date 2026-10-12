<?php

namespace App\Http\Controllers\Driver;

use App\Events\DriverLocationUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TelemetryController extends BaseDriverController
{
    /**
     * 1. Toggle Online / Offline Status
     * POST /driver/status/toggle
     */
    public function toggleStatus(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'isOnline'   => 'required|boolean',
            'currentLat' => 'nullable|numeric',
            'currentLng' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $isOnline = (bool) $request->input('isOnline');
        $driver->online_status = $isOnline ? 'online' : 'offline';

        if ($request->filled('currentLat') && $request->filled('currentLng')) {
            $lat = (float) $request->input('currentLat');
            $lng = (float) $request->input('currentLng');

            // Guard against Android/iOS emulator default US coordinates (Mountain View: ~37.422, -122.084)
            if ((abs($lat - 37.422) < 0.2 && abs($lng - (-122.084)) < 0.2) || $lng < 0) {
                $lat = 9.9252;
                $lng = 78.1198;
            }

            $driver->current_lat = $lat;
            $driver->current_lng = $lng;
        } elseif ($request->filled('currentLat')) {
            $driver->current_lat = $request->input('currentLat');
        } elseif ($request->filled('currentLng')) {
            $driver->current_lng = $request->input('currentLng');
        }

        if ($isOnline) {
            if (!$driver->active_session_started_at || $driver->online_status === 'offline') {
                $driver->active_session_started_at = now();
            }
        } else {
            $driver->active_session_started_at = null;
        }

        $driver->save();

        $sessionStart = $driver->active_session_started_at ?: now();

        return $this->successResponse([
            'isOnline'               => $isOnline,
            'status'                 => $isOnline ? 'SEARCHING_FOR_RIDES' : 'OFFLINE',
            'activeSessionStartedAt' => $sessionStart->toIso8601ZuluString(),
        ]);
    }

    /**
     * 2. Batch Location Telemetry Update (HTTP Fallback)
     * POST /driver/telemetry/location
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $locations = $request->input('locations');

        // Allow single location object or array of locations
        if (!$locations && $request->filled('latitude')) {
            $locations = [$request->all()];
        }

        if (empty($locations) || !is_array($locations)) {
            return $this->errorResponse('Locations payload must be a non-empty list of points.', 'BAD_REQUEST', 400);
        }

        $latest = end($locations);
        $lat = $latest['latitude'] ?? $latest['lat'] ?? null;
        $lng = $latest['longitude'] ?? $latest['lng'] ?? null;
        $bearing = $latest['bearing'] ?? null;
        $speed = $latest['speed'] ?? null;

        if ($lat !== null && $lng !== null) {
            $lat = (float) $lat;
            $lng = (float) $lng;

            // Guard against Android/iOS emulator default US coordinates (Mountain View: ~37.422, -122.084)
            if ((abs($lat - 37.422) < 0.2 && abs($lng - (-122.084)) < 0.2) || $lng < 0) {
                $lat = 9.9252;
                $lng = 78.1198;
            }

            $driver->current_lat = $lat;
            $driver->current_lng = $lng;
            if ($bearing !== null) $driver->bearing = $bearing;
            if ($speed !== null) $driver->speed = $speed;
            $driver->save();

            // Find current active ride if any
            $currentRide = $driver->rides()
                ->whereIn('status', ['driver_assigned', 'driver_arrived', 'in_progress'])
                ->latest()
                ->first();

            // Dispatch DRIVER_LOCATION_UPDATE event
            event(new DriverLocationUpdate([
                'latitude'      => (float) $lat,
                'longitude'     => (float) $lng,
                'bearing'       => (float) ($bearing ?? 0),
                'speed'         => (float) ($speed ?? 0),
                'accuracy'      => (float) ($latest['accuracy'] ?? 4.5),
                'timestamp'     => (int) ($latest['timestamp'] ?? (time() * 1000)),
                'currentRideId' => $currentRide ? ($currentRide->ride_number ?: ('RIDE-' . $currentRide->id)) : null,
            ]));
        }

        return $this->successResponse(
            null,
            'Location updated successfully.',
            200
        );
    }
}
