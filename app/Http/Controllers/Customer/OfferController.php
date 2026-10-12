<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OfferController extends Controller
{
    public function getAvailableOffers(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');

        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized. Customer session not found.', 401);
        }

        $validator = Validator::make($request->all(), [
            'vehicle_type' => ['nullable', 'in:bike,auto,mini,sedan,suv'],
            'estimated_fare' => ['nullable', 'numeric', 'min:0'],
            'ride_id' => ['nullable', 'exists:rides,id'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $validated = $validator->validated();

        // Get ride details if ride_id provided
        $estimatedFare = $validated['estimated_fare'] ?? 0;
        $vehicleType = $validated['vehicle_type'] ?? null;

        if ($validated['ride_id'] ?? null) {
            $ride = Ride::where('id', $validated['ride_id'])
                ->where('customer_id', $customer->id)
                ->first();

            if ($ride) {
                $estimatedFare = $estimatedFare ?: (float) ($ride->total_fare ?? $ride->base_fare ?? 0);
                $vehicleType = $vehicleType ?: $ride->vehicle_type;
            }
        }

        // Get active promotions
        $now = now();
        $promotions = Promotion::where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $now);
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhere('total_used', '<', 'usage_limit');
            })
            ->get();

        $offers = $promotions->filter(function ($promo) use ($estimatedFare, $vehicleType) {
            // Check minimum ride amount
            if ($promo->min_ride_amount > 0 && $estimatedFare < $promo->min_ride_amount) {
                return false;
            }

            // Additional logic could be added here for vehicle-type specific offers
            // For now, all active promotions are applicable to all vehicle types

            return true;
        })->map(function ($promo) {
            return [
                'id' => $promo->id,
                'code' => $promo->code,
                'title' => $promo->title,
                'description' => $promo->description,
                'discount_type' => $promo->discount_type, // 'percentage' or 'fixed'
                'discount_value' => (float) $promo->discount_value,
                'max_discount' => $promo->max_discount ? (float) $promo->max_discount : null,
                'min_ride_amount' => (float) $promo->min_ride_amount,
                'expires_at' => $promo->expires_at?->format('Y-m-d\TH:i:s\Z'),
                'applicable_vehicle_types' => ['bike', 'auto', 'mini', 'sedan', 'suv'], // Could be made dynamic later
            ];
        })->values();

        return $this->successResponse([
            'offers' => $offers,
            'estimated_fare' => $estimatedFare,
            'vehicle_type' => $vehicleType,
        ], 'Available offers retrieved successfully', 200);
    }

    public function validatePromoCode(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');

        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized. Customer session not found.', 401);
        }

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:20'],
            'estimated_fare' => ['required', 'numeric', 'min:0'],
            'vehicle_type' => ['nullable', 'in:bike,auto,mini,sedan,suv'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $validated = $validator->validated();

        $now = now();
        $promo = Promotion::where('code', strtoupper($validated['code']))
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $now);
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhere('total_used', '<', 'usage_limit');
            })
            ->first();

        if (!$promo) {
            return $this->errorResponse('INVALID_PROMO_CODE', 'Invalid or expired promo code', 400);
        }

        // Check minimum ride amount
        if ($promo->min_ride_amount > 0 && $validated['estimated_fare'] < $promo->min_ride_amount) {
            return $this->errorResponse('MIN_FARE_NOT_MET', "Minimum ride amount of ₹{$promo->min_ride_amount} required for this offer", 400);
        }

        // Calculate discount
        $discount = 0;
        if ($promo->discount_type === 'percentage') {
            $discount = ($validated['estimated_fare'] * $promo->discount_value) / 100;
            if ($promo->max_discount && $discount > $promo->max_discount) {
                $discount = (float) $promo->max_discount;
            }
        } else {
            $discount = (float) $promo->discount_value;
            if ($discount > $validated['estimated_fare']) {
                $discount = $validated['estimated_fare'];
            }
        }

        return $this->successResponse([
            'promo_code' => $promo->code,
            'title' => $promo->title,
            'discount_type' => $promo->discount_type,
            'discount_value' => (float) $promo->discount_value,
            'calculated_discount' => round($discount, 2),
            'max_discount' => $promo->max_discount ? (float) $promo->max_discount : null,
            'final_fare' => round($validated['estimated_fare'] - $discount, 2),
        ], 'Promo code validated successfully', 200);
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