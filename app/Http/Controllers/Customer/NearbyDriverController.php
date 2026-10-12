<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Module 4: Real-time Ghost Cars & Nearby Drivers
 */
class NearbyDriverController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // 4.1  GET /drivers/nearby
    // ──────────────────────────────────────────────────────────────────────────
    public function nearby(Request $request): JsonResponse
    {
        $lat = $request->input('latitude') ?? $request->input('lat');
        $lng = $request->input('longitude') ?? $request->input('lng');

        $validator = Validator::make([
            'latitude' => $lat,
            'longitude' => $lng,
            'radius' => $request->input('radius'),
            'category' => $request->input('category'),
        ], [
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius'    => ['nullable', 'numeric', 'min:100', 'max:50000'],
            'category'  => ['nullable', 'in:daily,rentals,outstation,all'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $lat      = (float) $lat;
        $lng      = (float) $lng;
        $radius   = (float) $request->input('radius', 3000); // metres
        $category = $request->input('category', 'all');

        // Fetch online drivers with their vehicles
        $query = Driver::with(['vehicle'])
            ->where('online_status', true)
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng');

        $drivers = $query->get();

        // Filter by radius (haversine)
        $radiusKm   = $radius / 1000;
        $nearbyList = [];

        foreach ($drivers as $driver) {
            $dLat = (float) $driver->current_lat;
            $dLng = (float) $driver->current_lng;
            $dist = $this->haversineDistance($lat, $lng, $dLat, $dLng);

            if ($dist <= $radiusKm) {
                $vehicleType = $driver->vehicle?->vehicle_type ?? 'sedan';
                $serviceType = $driver->vehicle?->service_type ?? 'daily';

                // Filter by category
                if ($category !== 'all') {
                    if ($category === 'daily' && !in_array($serviceType, ['daily', null])) continue;
                    if ($category === 'rentals' && $serviceType !== 'rentals') continue;
                    if ($category === 'outstation' && $serviceType !== 'outstation') continue;
                }

                $nearbyList[] = [
                    'driverId'    => $driver->id,
                    'driverCode'  => $driver->driver_code,
                    'latitude'    => $dLat,
                    'longitude'   => $dLng,
                    'bearing'     => (float) ($driver->bearing ?? 0),
                    'speed'       => (float) ($driver->speed ?? 0),
                    'vehicleType' => $vehicleType,
                    'distanceKm'  => round($dist, 2),
                    'eta'         => (int) max(1, round($dist * 2.5)) . ' mins',
                    'rating'      => (float) ($driver->rating ?? 4.5),
                ];
            }
        }

        // If no real drivers, return ghost/simulated drivers for UI demo
        if (empty($nearbyList)) {
            $nearbyList = $this->simulateNearbyDrivers($lat, $lng, $category);
        }

        return $this->successResponse([
            'drivers' => $nearbyList,
            'count'   => count($nearbyList),
            'center'  => ['latitude' => $lat, 'longitude' => $lng],
            'radius'  => $radius,
        ], 'Nearby drivers retrieved');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function simulateNearbyDrivers(float $lat, float $lng, string $category): array
    {
        $types    = match ($category) {
            'daily'      => ['bike', 'auto', 'mini', 'sedan'],
            'rentals'    => ['sedan', 'suv'],
            'outstation' => ['sedan', 'suv'],
            default      => ['bike', 'auto', 'mini', 'sedan', 'suv'],
        };

        $drivers = [];
        $count   = rand(3, 8);

        for ($i = 1; $i <= $count; $i++) {
            $offsetLat = (rand(-300, 300) / 100000);
            $offsetLng = (rand(-300, 300) / 100000);
            $drivers[] = [
                'driverId'    => 'ghost_' . $i,
                'driverCode'  => 'DRV-GHOST-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'latitude'    => round($lat + $offsetLat, 7),
                'longitude'   => round($lng + $offsetLng, 7),
                'bearing'     => (float) rand(0, 359),
                'speed'       => (float) rand(10, 40),
                'vehicleType' => $types[array_rand($types)],
                'distanceKm'  => round(abs($offsetLat) * 111 + abs($offsetLng) * 85, 2),
                'eta'         => rand(2, 8) . ' mins',
                'rating'      => round(4.0 + (rand(0, 10) / 10), 1),
            ];
        }

        return $drivers;
    }

    protected function successResponse($data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $r = ['success' => true, 'statusCode' => $statusCode];
        if ($message) $r['message'] = $message;
        if ($data !== null) $r['data'] = $data;
        return response()->json($r, $statusCode);
    }

    protected function errorResponse(string $code, string $message, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success'    => false,
            'statusCode' => $statusCode,
            'error'      => ['code' => $code, 'message' => $message],
        ], $statusCode);
    }
}
