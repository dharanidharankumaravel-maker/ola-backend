<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Module 5: Fare Estimates & Promo Offers
 */
class FareEstimateController extends Controller
{
    // Fare configuration per vehicle type
    private array $fareConfig = [
        'bike'  => ['base' => 20.00, 'perKm' => 8.00,  'perMin' => 1.50, 'capacity' => 1],
        'auto'  => ['base' => 30.00, 'perKm' => 12.00, 'perMin' => 2.00, 'capacity' => 3],
        'mini'  => ['base' => 50.00, 'perKm' => 15.00, 'perMin' => 2.50, 'capacity' => 4],
        'sedan' => ['base' => 70.00, 'perKm' => 18.00, 'perMin' => 3.00, 'capacity' => 4],
        'suv'   => ['base' => 100.00,'perKm' => 22.00, 'perMin' => 4.00, 'capacity' => 6],
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // 5.1  POST /rides/estimates
    // ──────────────────────────────────────────────────────────────────────────
    public function calculate(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $pLat = $request->input('pickup.latitude') ?? $request->input('pickup.lat');
        $pLng = $request->input('pickup.longitude') ?? $request->input('pickup.lng');
        $dLat = $request->input('destination.latitude') ?? $request->input('destination.lat') ?? $request->input('drop.latitude') ?? $request->input('drop.lat');
        $dLng = $request->input('destination.longitude') ?? $request->input('destination.lng') ?? $request->input('drop.longitude') ?? $request->input('drop.lng');
        $category = $request->input('category') ?? $request->input('ride_category', 'daily');

        $validator = Validator::make([
            'pickup' => ['latitude' => $pLat, 'longitude' => $pLng],
            'destination' => ['latitude' => $dLat, 'longitude' => $dLng],
            'category' => $category,
            'rentalHours' => $request->input('rentalHours'),
        ], [
            'pickup.latitude'       => ['required', 'numeric', 'between:-90,90'],
            'pickup.longitude'      => ['required', 'numeric', 'between:-180,180'],
            'destination.latitude'  => ['required', 'numeric', 'between:-90,90'],
            'destination.longitude' => ['required', 'numeric', 'between:-180,180'],
            'category'              => ['nullable', 'in:daily,rentals,outstation'],
            'rentalHours'           => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $oLat     = (float) $pLat;
        $oLng     = (float) $pLng;
        $dLat     = (float) $dLat;
        $dLng     = (float) $dLng;
        $rentalHours = (int) $request->input('rentalHours', 4);

        // Calculate distance
        $distKm   = $this->haversineDistance($oLat, $oLng, $dLat, $dLng);
        $durationMins = (int) max(5, round($distKm * 2.8));

        $estimates = [];

        // Build estimates for each vehicle type
        foreach ($this->fareConfig as $type => $config) {
            $estimate = $this->buildEstimate($type, $config, $distKm, $durationMins, $category, $rentalHours);
            if ($estimate) {
                $estimates[] = $estimate;
            }
        }

        return $this->successResponse([
            'estimates' => $estimates,
            'route'     => [
                'distanceKm'   => round($distKm, 2),
                'durationMins' => $durationMins,
            ],
            'category'  => $category,
        ], 'Fare estimates calculated');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function buildEstimate(
        string $type,
        array $config,
        float $distKm,
        int $durationMins,
        string $category,
        int $rentalHours
    ): ?array {
        // Skip bikes for outstation / rentals (optional business logic)
        if ($type === 'bike' && in_array($category, ['rentals', 'outstation'])) {
            return null;
        }

        $baseFare     = $config['base'];
        $distanceFare = round($distKm * $config['perKm'], 2);
        $timeFare     = round(($durationMins * $config['perMin']), 2);

        if ($category === 'rentals') {
            // Rental: flat rate per hour
            $rentalRate   = round($config['base'] * 0.7 + $config['perKm'] * 15, 2); // per hour
            $baseFare     = round($rentalRate * $rentalHours, 2);
            $distanceFare = 0;
            $timeFare     = 0;
        } elseif ($category === 'outstation') {
            // Outstation: 20% premium
            $distanceFare = round($distanceFare * 1.2, 2);
        }

        $totalFare  = round($baseFare + $distanceFare + $timeFare, 2);
        $eta        = rand(3, 10) . ' mins';
        $available  = rand(2, 8);

        return [
            'vehicleType'   => $type,
            'name'          => ucfirst($type),
            'capacity'      => $config['capacity'],
            'category'      => $category,
            'fareBreakup'   => [
                'baseFare'     => $baseFare,
                'distanceFare' => $distanceFare,
                'timeFare'     => $timeFare,
                'taxes'        => 0.0,
                'surge'        => 0.0,
                'discount'     => 0.0,
                'totalFare'    => $totalFare,
            ],
            'totalFare'     => $totalFare,
            'currency'      => 'INR',
            'eta'           => $eta,
            'available'     => $available,
            'isSurge'       => false,
            'surgeMultiplier' => 1.0,
            'perKmRate'     => $config['perKm'],
            'perMinRate'    => $config['perMin'],
        ];
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
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
