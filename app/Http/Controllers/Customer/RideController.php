<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Module 6: Ride Lifecycle & Booking
 */
class RideController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // 6.1  POST /rides/book
    // ──────────────────────────────────────────────────────────────────────────
    public function book(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $pLat = $request->input('pickup.latitude') ?? $request->input('pickup.lat');
        $pLng = $request->input('pickup.longitude') ?? $request->input('pickup.lng');
        $pAddr = $request->input('pickup.formattedAddress') ?? $request->input('pickup.address') ?? 'Pickup Location';

        $dLat = $request->input('destination.latitude') ?? $request->input('destination.lat') ?? $request->input('drop.latitude') ?? $request->input('drop.lat');
        $dLng = $request->input('destination.longitude') ?? $request->input('destination.lng') ?? $request->input('drop.longitude') ?? $request->input('drop.lng');
        $dAddr = $request->input('destination.formattedAddress') ?? $request->input('destination.address') ?? $request->input('drop.formattedAddress') ?? $request->input('drop.address') ?? 'Dropoff Location';

        $vehicleType = $request->input('vehicleType') ?? $request->input('vehicle_type');
        $paymentMethod = $request->input('paymentMethod') ?? $request->input('payment_method');
        $category = $request->input('category') ?? $request->input('ride_category', 'daily');
        $promoCode = $request->input('promoCode') ?? $request->input('promo_code');
        $scheduledAt = $request->input('scheduledAt') ?? $request->input('scheduled_at');
        $tipAmount = (float) ($request->input('tipAmount') ?? $request->input('tip_amount', 0));
        $estimatedFare = $request->input('estimatedFare') ?? $request->input('estimated_fare');

        $validator = Validator::make([
            'pickup' => ['latitude' => $pLat, 'longitude' => $pLng, 'formattedAddress' => $pAddr],
            'destination' => ['latitude' => $dLat, 'longitude' => $dLng, 'formattedAddress' => $dAddr],
            'vehicleType' => $vehicleType,
            'category' => $category,
            'paymentMethod' => $paymentMethod,
            'promoCode' => $promoCode,
            'scheduledAt' => $scheduledAt,
            'tipAmount' => $tipAmount,
            'estimatedFare' => $estimatedFare,
        ], [
            'pickup.latitude'           => ['required', 'numeric', 'between:-90,90'],
            'pickup.longitude'          => ['required', 'numeric', 'between:-180,180'],
            'pickup.formattedAddress'   => ['nullable', 'string', 'max:500'],
            'destination.latitude'      => ['required', 'numeric', 'between:-90,90'],
            'destination.longitude'     => ['required', 'numeric', 'between:-180,180'],
            'destination.formattedAddress' => ['nullable', 'string', 'max:500'],
            'vehicleType'               => ['required', 'in:bike,auto,mini,sedan,suv'],
            'category'                  => ['nullable', 'in:daily,rentals,outstation,parcel'],
            'paymentMethod'             => ['required', 'in:cash,wallet,upi,card'],
            'promoCode'                 => ['nullable', 'string', 'max:30'],
            'scheduledAt'               => ['nullable', 'date', 'after:now'],
            'tipAmount'                 => ['nullable', 'numeric', 'min:0'],
            'estimatedFare'             => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $distKm = $this->haversineDistance(
            (float) $pLat,
            (float) $pLng,
            (float) $dLat,
            (float) $dLng
        );

        $durationMins = (int) max(5, round($distKm * 2.8));
        $fareConfig   = $this->getFareConfig($vehicleType);
        $baseFare     = $fareConfig['base'];
        $distFare     = round($distKm * $fareConfig['perKm'], 2);
        $timeFare     = round($durationMins * $fareConfig['perMin'], 2);
        $totalFare    = $estimatedFare ?? round($baseFare + $distFare + $timeFare, 2);
        $otp          = (string) rand(1000, 9999);

        // Validate wallet if payment method is wallet
        if ($paymentMethod === 'wallet') {
            if ((float) $customer->wallet_balance < $totalFare) {
                return $this->errorResponse('INSUFFICIENT_BALANCE', 'Insufficient wallet balance.', 400);
            }
        }

        $ride = DB::transaction(function () use (
            $customer, $vehicleType, $category, $pLat, $pLng, $pAddr,
            $dLat, $dLng, $dAddr, $distKm, $durationMins,
            $baseFare, $distFare, $timeFare, $totalFare, $tipAmount, $otp,
            $paymentMethod, $promoCode, $scheduledAt
        ) {
            return Ride::create([
                'customer_id'     => $customer->id,
                'vehicle_type'    => $vehicleType,
                'ride_category'   => $category ?? 'daily',
                'pickup_lat'      => $pLat,
                'pickup_lng'      => $pLng,
                'pickup_address'  => $pAddr,
                'dropoff_lat'     => $dLat,
                'dropoff_lng'     => $dLng,
                'dropoff_address' => $dAddr,
                'distance_km'     => round($distKm, 2),
                'duration_mins'   => $durationMins,
                'base_fare'       => $baseFare,
                'distance_fare'   => $distFare,
                'time_fare'       => $timeFare,
                'total_fare'      => $totalFare,
                'tip_amount'      => $tipAmount,
                'payment_method'  => $paymentMethod,
                'payment_status'  => 'pending',
                'promo_code'      => $promoCode,
                'status'          => 'requested',
                'otp'             => $otp,
                'scheduled_at'    => $scheduledAt ? Carbon::parse($scheduledAt) : null,
            ]);
        });

        return $this->successResponse([
            'id'            => $ride->id,
            'rideId'        => $ride->ride_number,
            'rideNumber'    => $ride->ride_number,
            'status'        => 'requested',
            'otp'           => $otp,
            'estimatedFare' => $totalFare,
            'currency'      => 'INR',
            'eta'           => rand(3, 8) . ' mins',
            'pickup'        => [
                'latitude'  => (float) $ride->pickup_lat,
                'longitude' => (float) $ride->pickup_lng,
                'address'   => $ride->pickup_address,
            ],
            'destination' => [
                'latitude'  => (float) $ride->dropoff_lat,
                'longitude' => (float) $ride->dropoff_lng,
                'address'   => $ride->dropoff_address,
            ],
            'vehicleType'   => $ride->vehicle_type,
            'paymentMethod' => $ride->payment_method,
            'distanceKm'    => (float) $ride->distance_km,
            'durationMins'  => $ride->duration_mins,
            'scheduledAt'   => $ride->scheduled_at?->format('Y-m-d\TH:i:s\Z'),
            'createdAt'     => $ride->created_at->format('Y-m-d\TH:i:s\Z'),
        ], 'Ride booked successfully. Searching for drivers…', 201);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6.2  GET /rides/current
    // ──────────────────────────────────────────────────────────────────────────
    public function current(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $activeStatuses = ['requested', 'driver_assigned', 'driver_arrived', 'in_progress'];

        $ride = Ride::with(['driver', 'vehicle'])
            ->where('customer_id', $customer->id)
            ->whereIn('status', $activeStatuses)
            ->latest()
            ->first();

        if (!$ride) {
            return $this->successResponse(['ride' => null], 'No active ride');
        }

        return $this->successResponse([
            'ride' => $this->formatRide($ride),
        ], 'Active ride retrieved');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6.3  PATCH /rides/:id/tip
    // ──────────────────────────────────────────────────────────────────────────
    public function updateTip(Request $request, string $id): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $tip = $request->input('tipAmount') ?? $request->input('tip_amount');

        $validator = Validator::make(['tipAmount' => $tip], [
            'tipAmount' => ['required', 'numeric', 'min:0', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $ride = $this->findRideForCustomer($id, $customer->id);
        if (!$ride) {
            return $this->errorResponse('NOT_FOUND', 'Ride not found.', 404);
        }

        $ride->update(['tip_amount' => (float) $tip]);
        $ride->refresh()->load(['driver', 'vehicle']);

        return $this->successResponse([
            'ride' => $this->formatRide($ride),
        ], 'Tip updated successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6.4  POST /rides/:id/cancel
    // ──────────────────────────────────────────────────────────────────────────
    public function cancel(Request $request, string $id): JsonResponse
    {
        if ($request->attributes->get('driver')) {
            return app(\App\Http\Controllers\Driver\RideController::class)->cancel($request, $id);
        }

        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $validator = Validator::make($request->all(), [
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $ride = $this->findRideForCustomer($id, $customer->id);
        if (!$ride) {
            return $this->errorResponse('NOT_FOUND', 'Ride not found.', 404);
        }

        $cancellableStatuses = ['requested', 'driver_assigned', 'driver_arrived'];
        if (!in_array($ride->status, $cancellableStatuses)) {
            return $this->errorResponse(
                'CANNOT_CANCEL',
                'Ride cannot be cancelled in its current state (' . $ride->status . ').',
                400
            );
        }

        $cancellationFee = ($ride->status === 'driver_arrived') ? 50.0 : 0.0;

        $ride->update([
            'status'                   => 'cancelled',
            'cancelled_by'             => 'customer',
            'cancel_reason'            => $request->input('reason', 'Customer cancelled'),
            'cancellation_fee'         => $cancellationFee,
            'cancellation_fee_eligible' => $cancellationFee > 0,
            'cancelled_at'             => now(),
        ]);

        // Deduct cancellation fee from wallet if applicable
        if ($cancellationFee > 0 && (float) $customer->wallet_balance >= $cancellationFee) {
            $customer->decrement('wallet_balance', $cancellationFee);

            WalletTransaction::create([
                'customer_id' => $customer->id,
                'ride_id'     => $ride->id,
                'type'        => 'debit',
                'amount'      => $cancellationFee,
                'description' => 'Cancellation fee for ride ' . $ride->ride_number,
                'status'      => 'completed',
            ]);
        }

        return $this->successResponse([
            'rideId'                  => $ride->ride_number,
            'status'                  => 'cancelled',
            'cancellationFeeEligible' => $cancellationFee > 0,
            'cancellationFee'         => $cancellationFee,
        ], 'Ride cancelled successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6.5  GET /rides/history
    // ──────────────────────────────────────────────────────────────────────────
    public function history(Request $request): JsonResponse
    {
        if ($request->attributes->get('driver')) {
            return app(\App\Http\Controllers\Driver\RideController::class)->history($request);
        }

        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $page   = (int) $request->input('page', 1);
        $limit  = (int) $request->input('limit', 10);
        $status = $request->input('status');

        $query = Ride::with(['driver', 'vehicle'])
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['completed', 'cancelled'])
            ->latest();

        if ($status && in_array($status, ['completed', 'cancelled'])) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $rides = $query->skip(($page - 1) * $limit)->take($limit)->get();

        return $this->successResponse(
            $rides->map(fn($r) => $this->formatRideHistory($r))->values(),
            'Ride history retrieved',
            200,
            ['total' => $total, 'page' => $page, 'limit' => $limit, 'totalPages' => (int) ceil($total / $limit)]
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6.6  Scheduled Rides
    // ──────────────────────────────────────────────────────────────────────────

    /** GET /rides/scheduled */
    public function scheduledRides(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $rides = Ride::where('customer_id', $customer->id)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->where('status', 'requested')
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn($r) => $this->formatRideHistory($r));

        return $this->successResponse(['rides' => $rides], 'Scheduled rides retrieved');
    }

    /** DELETE /rides/scheduled/:id */
    public function cancelScheduled(Request $request, string $id): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $ride = Ride::where('customer_id', $customer->id)
            ->whereNotNull('scheduled_at')
            ->where('status', 'requested')
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('ride_number', $id);
            })
            ->first();

        if (!$ride) {
            return $this->errorResponse('NOT_FOUND', 'Scheduled ride not found.', 404);
        }

        $ride->update([
            'status'       => 'cancelled',
            'cancelled_by' => 'customer',
            'cancel_reason' => 'Customer cancelled scheduled ride',
            'cancelled_at'  => now(),
        ]);

        return $this->successResponse(null, 'Scheduled ride cancelled');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6.6b GET /rides/:id  (Ride Detail)
    // ──────────────────────────────────────────────────────────────────────────
    public function show(Request $request, string $id): JsonResponse
    {
        if ($request->attributes->get('driver')) {
            return app(\App\Http\Controllers\Driver\RideController::class)->show($request, $id);
        }

        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $ride = $this->findRideForCustomer($id, $customer->id);
        if (!$ride) {
            return $this->errorResponse('NOT_FOUND', 'Ride not found.', 404);
        }

        $ride->load(['driver', 'vehicle']);
        return $this->successResponse($this->formatRide($ride), 'Ride details retrieved');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function findRideForCustomer(string $id, int $customerId): ?Ride
    {
        return Ride::with(['driver', 'vehicle'])
            ->where('customer_id', $customerId)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('ride_number', $id);
            })
            ->first();
    }

    private function formatRide(Ride $ride): array
    {
        $driver  = $ride->driver;
        $vehicle = $ride->vehicle;

        return [
            'id'            => $ride->ride_number ?? 'RIDE-' . $ride->id,
            'status'        => $ride->status,
            'otp'           => $ride->otp,
            'vehicleType'   => $ride->vehicle_type,
            'category'      => $ride->ride_category ?? 'daily',
            'pickup'        => [
                'latitude'  => $ride->pickup_lat  ? (float) $ride->pickup_lat  : null,
                'longitude' => $ride->pickup_lng  ? (float) $ride->pickup_lng  : null,
                'address'   => $ride->pickup_address,
            ],
            'destination' => [
                'latitude'  => $ride->dropoff_lat ? (float) $ride->dropoff_lat : null,
                'longitude' => $ride->dropoff_lng ? (float) $ride->dropoff_lng : null,
                'address'   => $ride->dropoff_address,
            ],
            'driver' => $driver ? [
                'id'       => $driver->driver_code,
                'name'     => $driver->full_name,
                'phone'    => $driver->phone,
                'avatar'   => $driver->avatar,
                'rating'   => (float) ($driver->rating ?? 4.5),
                'vehicle'  => $vehicle ? [
                    'regNumber'   => $vehicle->reg_number,
                    'make'        => $vehicle->make,
                    'model'       => $vehicle->model,
                    'color'       => $vehicle->color,
                    'year'        => $vehicle->year,
                ] : null,
                'location' => [
                    'latitude'  => $driver->current_lat  ? (float) $driver->current_lat  : null,
                    'longitude' => $driver->current_lng  ? (float) $driver->current_lng  : null,
                    'bearing'   => (float) ($driver->bearing ?? 0),
                ],
            ] : null,
            'fareBreakup' => [
                'baseFare'     => (float) ($ride->base_fare    ?? 0),
                'distanceFare' => (float) ($ride->distance_fare ?? 0),
                'timeFare'     => (float) ($ride->time_fare    ?? 0),
                'taxes'        => (float) ($ride->tax_amount   ?? 0),
                'surge'        => (float) ($ride->surge_amount ?? 0),
                'discount'     => (float) ($ride->discount_amount ?? 0),
                'tip'          => (float) ($ride->tip_amount   ?? 0),
                'totalFare'    => (float) ($ride->total_fare   ?? 0),
            ],
            'paymentMethod'  => $ride->payment_method,
            'paymentStatus'  => $ride->payment_status,
            'distanceKm'     => $ride->distance_km  ? (float) $ride->distance_km  : null,
            'durationMins'   => $ride->duration_mins,
            'polyline'       => $ride->polyline,
            'scheduledAt'    => $ride->scheduled_at?->format('Y-m-d\TH:i:s\Z'),
            'acceptedAt'     => $ride->accepted_at?->format('Y-m-d\TH:i:s\Z'),
            'arrivedAt'      => $ride->arrived_at?->format('Y-m-d\TH:i:s\Z'),
            'startedAt'      => $ride->started_at?->format('Y-m-d\TH:i:s\Z'),
            'completedAt'    => $ride->completed_at?->format('Y-m-d\TH:i:s\Z'),
            'cancelledAt'    => $ride->cancelled_at?->format('Y-m-d\TH:i:s\Z'),
            'cancelReason'   => $ride->cancel_reason,
            'driverRating'   => $ride->driver_rating ? (float) $ride->driver_rating : null,
            'driverReview'   => $ride->driver_review,
            'createdAt'      => $ride->created_at?->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    private function formatRideHistory(Ride $ride): array
    {
        $dateStr = $ride->created_at
            ? ($ride->created_at->isToday() ? 'Today' : ($ride->created_at->isYesterday() ? 'Yesterday' : $ride->created_at->format('M d, Y')))
            : 'Today';

        return [
            'id'          => $ride->ride_number ?? 'RIDE-' . $ride->id,
            'status'      => $ride->status,
            'vehicleType' => $ride->vehicle_type,
            'pickup'      => $ride->pickup_address,
            'destination' => $ride->dropoff_address,
            'fare'        => '₹' . number_format((float) ($ride->total_fare ?? 0), 0),
            'totalFare'   => (float) ($ride->total_fare ?? 0),
            'distanceKm'  => $ride->distance_km ? (float) $ride->distance_km : null,
            'durationMins' => $ride->duration_mins,
            'paymentMethod' => strtoupper($ride->payment_method ?? 'CASH'),
            'paymentStatus' => ucfirst($ride->payment_status ?? 'pending'),
            'date'         => $dateStr,
            'time'         => $ride->created_at?->format('H:i'),
            'driverRating' => $ride->driver_rating ? (float) $ride->driver_rating : null,
            'driverReview' => $ride->driver_review,
            'cancelReason' => $ride->cancel_reason,
            'scheduledAt'  => $ride->scheduled_at?->format('Y-m-d\TH:i:s\Z'),
            'createdAt'    => $ride->created_at?->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    private function getFareConfig(string $type): array
    {
        return [
            'bike'  => ['base' => 20.00, 'perKm' => 8.00,  'perMin' => 1.50],
            'auto'  => ['base' => 30.00, 'perKm' => 12.00, 'perMin' => 2.00],
            'mini'  => ['base' => 50.00, 'perKm' => 15.00, 'perMin' => 2.50],
            'sedan' => ['base' => 70.00, 'perKm' => 18.00, 'perMin' => 3.00],
            'suv'   => ['base' => 100.00,'perKm' => 22.00, 'perMin' => 4.00],
        ][$type] ?? ['base' => 50.00, 'perKm' => 15.00, 'perMin' => 2.50];
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    protected function successResponse($data = null, ?string $message = null, int $statusCode = 200, array $meta = []): JsonResponse
    {
        $r = ['success' => true, 'statusCode' => $statusCode];
        if ($message) $r['message'] = $message;
        if ($data !== null) $r['data'] = $data;
        if (!empty($meta)) $r['meta'] = $meta;
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
