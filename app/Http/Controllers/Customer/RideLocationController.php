<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RideLocationController extends Controller
{
    public function storeCurrentLocation(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');

        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized. Customer session not found.', 401);
        }

        $validator = Validator::make($request->all(), [
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address'   => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $validated = $validator->validated();

        $ride = Ride::create([
            'customer_id'   => $customer->id,
            'pickup_lat'    => $validated['latitude'],
            'pickup_lng'    => $validated['longitude'],
            'pickup_address'=> $validated['address'] ?? null,
            'status'        => 'requested',
        ]);

        return $this->successResponse([
            'ride_id'       => $ride->id,
            'ride_number'   => $ride->ride_number,
            'pickup_lat'    => (float) $ride->pickup_lat,
            'pickup_lng'    => (float) $ride->pickup_lng,
            'pickup_address'=> $ride->pickup_address,
            'status'        => $ride->status,
        ], 'Current location saved successfully', 201);
    }
 
    public function updateDestination(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');

        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized. Customer session not found.', 401);
        }

        $validator = Validator::make($request->all(), [
            'ride_id'    => ['required', 'exists:rides,id'],
            'latitude'   => ['required', 'numeric', 'between:-90,90'],
            'longitude'  => ['required', 'numeric', 'between:-180,180'],
            'address'    => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $validated = $validator->validated();

        $ride = Ride::where('id', $validated['ride_id'])
            ->where('customer_id', $customer->id)
            ->first();

        if (!$ride) {
            return $this->errorResponse('NOT_FOUND', 'Ride not found', 404);
        }

        $ride->update([
            'dropoff_lat'     => $validated['latitude'],
            'dropoff_lng'     => $validated['longitude'],
            'dropoff_address' => $validated['address'] ?? null,
        ]);

        return $this->successResponse([
            'ride_id'         => $ride->id,
            'ride_number'     => $ride->ride_number,
            'dropoff_lat'     => (float) $ride->dropoff_lat,
            'dropoff_lng'     => (float) $ride->dropoff_lng,
            'dropoff_address' => $ride->dropoff_address,
        ], 'Destination location saved successfully', 200);
    }

    public function getRideDetails(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');

        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized. Customer session not found.', 401);
        }

        $rideId = $request->input('ride_id');

        if (!$rideId) {
            return $this->errorResponse('VALIDATION_ERROR', 'Ride ID is required', 422);
        }

        $ride = Ride::where('id', $rideId)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$ride) {
            return $this->errorResponse('NOT_FOUND', 'Ride not found', 404);
        }

        return $this->successResponse([
            'ride_id'          => $ride->id,
            'ride_number'      => $ride->ride_number,
            'pickup_lat'       => $ride->pickup_lat ? (float) $ride->pickup_lat : null,
            'pickup_lng'       => $ride->pickup_lng ? (float) $ride->pickup_lng : null,
            'pickup_address'   => $ride->pickup_address,
            'dropoff_lat'      => $ride->dropoff_lat ? (float) $ride->dropoff_lat : null,
            'dropoff_lng'      => $ride->dropoff_lng ? (float) $ride->dropoff_lng : null,
            'dropoff_address'  => $ride->dropoff_address,
            'status'           => $ride->status,
            'distance_km'      => $ride->distance_km ? (float) $ride->distance_km : null,
            'total_fare'       => $ride->total_fare ? (float) $ride->total_fare : null,
            'driver_id'        => $ride->driver_id,
            'vehicle_type'     => $ride->vehicle_type,
            'created_at'       => $ride->created_at?->format('Y-m-d\TH:i:s\Z'),
        ], 'Ride details retrieved successfully', 200);
    }

    protected function successResponse($data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = ['success' => true, 'statusCode' => $statusCode];
        if ($message) $response['message'] = $message;
        if ($data) $response['data'] = $data;
        return response()->json($response, $statusCode);
    }

    protected function errorResponse(string $code, string $message, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'statusCode' => $statusCode,
            'error' => ['code' => $code, 'message' => $message],
        ], $statusCode);
    }
}