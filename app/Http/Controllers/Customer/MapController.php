<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerSavedPlace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Module 3: Map, Places & Locations
 */
class MapController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // 3.1  GET /map/search  (Place Search / Autocomplete)
    // ──────────────────────────────────────────────────────────────────────────
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q'         => ['required', 'string', 'min:1', 'max:200'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'limit'     => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $q         = trim($request->input('q'));
        $latitude  = $request->input('latitude');
        $longitude = $request->input('longitude');
        $limit     = (int) $request->input('limit', 8);

        // Simulated place search results (in production: call Google Places API)
        $places = $this->simulatePlaceSearch($q, $latitude, $longitude, $limit);

        return $this->successResponse([
            'places' => $places,
            'query'  => $q,
        ], 'Places retrieved successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3.2  GET /map/reverse-geocode
    // ──────────────────────────────────────────────────────────────────────────
    public function reverseGeocode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $lat = (float) $request->input('lat');
        $lng = (float) $request->input('lng');

        // Simulated reverse geocode (in production: call Google Geocoding API)
        $result = $this->simulateReverseGeocode($lat, $lng);

        return $this->successResponse($result, 'Address resolved successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3.3  POST /map/route
    // ──────────────────────────────────────────────────────────────────────────
    public function route(Request $request): JsonResponse
    {
        $oLat = $request->input('origin.latitude') ?? $request->input('origin.lat');
        $oLng = $request->input('origin.longitude') ?? $request->input('origin.lng');
        $dLat = $request->input('destination.latitude') ?? $request->input('destination.lat');
        $dLng = $request->input('destination.longitude') ?? $request->input('destination.lng');

        $validator = Validator::make([
            'origin' => ['latitude' => $oLat, 'longitude' => $oLng],
            'destination' => ['latitude' => $dLat, 'longitude' => $dLng],
        ], [
            'origin.latitude'        => ['required', 'numeric', 'between:-90,90'],
            'origin.longitude'       => ['required', 'numeric', 'between:-180,180'],
            'destination.latitude'   => ['required', 'numeric', 'between:-90,90'],
            'destination.longitude'  => ['required', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $oLat = (float) $oLat;
        $oLng = (float) $oLng;
        $dLat = (float) $dLat;
        $dLng = (float) $dLng;

        // Compute haversine distance approximation
        $distKm   = $this->haversineDistance($oLat, $oLng, $dLat, $dLng);
        $durationMins = (int) max(5, round($distKm * 2.8)); // avg speed ~21 km/h urban

        return $this->successResponse([
            'distance' => [
                'value' => round($distKm, 2),
                'unit'  => 'km',
                'text'  => round($distKm, 1) . ' km',
            ],
            'duration' => [
                'value' => $durationMins,
                'unit'  => 'mins',
                'text'  => $durationMins . ' mins',
            ],
            'polyline'  => $this->mockPolyline($oLat, $oLng, $dLat, $dLng),
            'overviewPolyline' => $this->mockPolyline($oLat, $oLng, $dLat, $dLng),
        ], 'Route calculated successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3.4  Saved Places
    // ──────────────────────────────────────────────────────────────────────────

    /** GET /users/saved-places */
    public function getSavedPlaces(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $places = CustomerSavedPlace::where('customer_id', $customer->id)
            ->orderBy('title')
            ->get()
            ->map(fn($p) => $this->formatPlace($p));

        return $this->successResponse(['places' => $places], 'Saved places retrieved');
    }

    /** POST /users/saved-places */
    public function addSavedPlace(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $title = $request->input('title') ?? $request->input('label') ?? 'Saved Place';
        $subtitle = $request->input('subtitle');
        $lat = $request->input('location.latitude') ?? $request->input('location.lat') ?? $request->input('latitude') ?? $request->input('lat');
        $lng = $request->input('location.longitude') ?? $request->input('location.lng') ?? $request->input('longitude') ?? $request->input('lng');
        $fAddr = $request->input('location.formattedAddress') ?? $request->input('location.address') ?? $request->input('formatted_address') ?? $request->input('address');
        $sAddr = $request->input('location.shortAddress') ?? $request->input('short_address') ?? $title;

        $validator = Validator::make([
            'title'     => $title,
            'latitude'  => $lat,
            'longitude' => $lng,
        ], [
            'title'     => ['required', 'string', 'max:100'],
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $place = CustomerSavedPlace::create([
            'customer_id'       => $customer->id,
            'title'             => $title,
            'subtitle'          => $subtitle,
            'latitude'          => (float) $lat,
            'longitude'         => (float) $lng,
            'formatted_address' => $fAddr,
            'short_address'     => $sAddr,
        ]);

        return $this->successResponse($this->formatPlace($place), 'Saved place added', 201);
    }

    /** DELETE /users/saved-places/:id */
    public function deleteSavedPlace(Request $request, string $id): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $place = CustomerSavedPlace::where('id', $id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$place) {
            return $this->errorResponse('NOT_FOUND', 'Saved place not found.', 404);
        }

        $place->delete();

        return $this->successResponse(null, 'Saved place removed');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function formatPlace(CustomerSavedPlace $p): array
    {
        return [
            'id'       => $p->id,
            'title'    => $p->title,
            'subtitle' => $p->subtitle,
            'location' => [
                'latitude'         => (float) $p->latitude,
                'longitude'        => (float) $p->longitude,
                'formattedAddress' => $p->formatted_address,
                'shortAddress'     => $p->short_address,
            ],
        ];
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function mockPolyline(float $oLat, float $oLng, float $dLat, float $dLng): string
    {
        // Returns a static encoded polyline placeholder
        // In production, integrate with Google Maps Directions API
        return 's_~`A}wvhNj@_Cd@eBh@}Ah@_Bt@yB';
    }

    private function simulatePlaceSearch(string $q, ?float $lat, ?float $lng, int $limit): array
    {
        // Simulated data — replace with Google Places API in production
        $seed = [
            ['placeId' => 'pl_01', 'title' => 'Chennai Central Station', 'subtitle' => 'Park Town, Chennai, Tamil Nadu', 'location' => ['latitude' => 13.0827, 'longitude' => 80.2707]],
            ['placeId' => 'pl_02', 'title' => 'Chennai Airport', 'subtitle' => 'Tirusulam, Chennai, Tamil Nadu', 'location' => ['latitude' => 12.9941, 'longitude' => 80.1709]],
            ['placeId' => 'pl_03', 'title' => 'Marina Beach', 'subtitle' => 'Marina, Chennai, Tamil Nadu', 'location' => ['latitude' => 13.0500, 'longitude' => 80.2824]],
            ['placeId' => 'pl_04', 'title' => 'T. Nagar Bus Stand', 'subtitle' => 'T. Nagar, Chennai, Tamil Nadu', 'location' => ['latitude' => 13.0418, 'longitude' => 80.2341]],
            ['placeId' => 'pl_05', 'title' => 'Anna Nagar Tower Park', 'subtitle' => 'Anna Nagar, Chennai, Tamil Nadu', 'location' => ['latitude' => 13.0850, 'longitude' => 80.2101]],
            ['placeId' => 'pl_06', 'title' => 'Adyar Signal', 'subtitle' => 'Adyar, Chennai, Tamil Nadu', 'location' => ['latitude' => 13.0012, 'longitude' => 80.2565]],
            ['placeId' => 'pl_07', 'title' => 'Velachery Metro Station', 'subtitle' => 'Velachery, Chennai, Tamil Nadu', 'location' => ['latitude' => 12.9796, 'longitude' => 80.2209]],
            ['placeId' => 'pl_08', 'title' => 'Phoenix Marketcity', 'subtitle' => 'Velachery, Chennai, Tamil Nadu', 'location' => ['latitude' => 12.9754, 'longitude' => 80.2194]],
        ];

        $q = strtolower($q);
        $filtered = array_filter($seed, fn($p) => str_contains(strtolower($p['title']), $q) || str_contains(strtolower($p['subtitle']), $q));

        return array_values(array_slice($filtered ?: $seed, 0, $limit));
    }

    private function simulateReverseGeocode(float $lat, float $lng): array
    {
        // Simulated — replace with Google Geocoding API in production
        return [
            'formattedAddress' => 'No 42, Anna Salai, Chennai, Tamil Nadu 600002',
            'shortAddress'     => 'Anna Salai, Chennai',
            'city'             => 'Chennai',
            'state'            => 'Tamil Nadu',
            'country'          => 'India',
            'postalCode'       => '600002',
            'latitude'         => $lat,
            'longitude'        => $lng,
        ];
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
