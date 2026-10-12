<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Module 7: Alo Parcel (Delivery Service)
 */
class ParcelController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // 7.1  POST /parcel/book
    // ──────────────────────────────────────────────────────────────────────────
    public function book(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $senderName = $request->input('senderName') ?? $request->input('sender_name') ?? $customer->name ?? 'Sender';
        $rawSenderPhone = $request->input('senderPhone') ?? $request->input('sender_phone') ?? $customer->phone ?? '9999999999';
        $senderPhone = substr(preg_replace('/[^\d]/', '', $rawSenderPhone), -10);

        $receiverName = $request->input('receiverName') ?? $request->input('receiver_name');
        $rawReceiverPhone = $request->input('receiverPhone') ?? $request->input('receiver_phone');
        $receiverPhone = $rawReceiverPhone ? substr(preg_replace('/[^\d]/', '', $rawReceiverPhone), -10) : null;

        $contents = $request->input('contents') ?? $request->input('package_type') ?? $request->input('description') ?? 'Parcel Package';
        
        $weightCat = $request->input('weightCategory') ?? $request->input('weight_category');
        if (!$weightCat && $request->filled('weight_kg')) {
            $kg = (float) $request->input('weight_kg');
            $weightCat = $kg <= 5 ? 'Up to 5 kg' : '5-15 kg';
        }

        $pLat = $request->input('pickup.latitude') ?? $request->input('pickup.lat');
        $pLng = $request->input('pickup.longitude') ?? $request->input('pickup.lng');
        $pAddr = $request->input('pickup.formattedAddress') ?? $request->input('pickup.address') ?? 'Pickup Location';

        $dLat = $request->input('destination.latitude') ?? $request->input('destination.lat') ?? $request->input('drop.latitude') ?? $request->input('drop.lat');
        $dLng = $request->input('destination.longitude') ?? $request->input('destination.lng') ?? $request->input('drop.longitude') ?? $request->input('drop.lng');
        $dAddr = $request->input('destination.formattedAddress') ?? $request->input('destination.address') ?? $request->input('drop.formattedAddress') ?? $request->input('drop.address') ?? 'Dropoff Location';

        $paymentMethod = $request->input('paymentMethod') ?? $request->input('payment_method', 'cash');

        $validator = Validator::make([
            'senderName'     => $senderName,
            'senderPhone'    => $senderPhone,
            'receiverName'   => $receiverName,
            'receiverPhone'  => $receiverPhone,
            'contents'       => $contents,
            'weightCategory' => $weightCat,
            'pickup'         => ['latitude' => $pLat, 'longitude' => $pLng, 'formattedAddress' => $pAddr],
            'destination'    => ['latitude' => $dLat, 'longitude' => $dLng, 'formattedAddress' => $dAddr],
            'paymentMethod'  => $paymentMethod,
        ], [
            // Sender / receiver
            'senderName'                 => ['required', 'string', 'max:100'],
            'senderPhone'                => ['required', 'regex:/^\d{10}$/'],
            'receiverName'               => ['required', 'string', 'max:100'],
            'receiverPhone'              => ['required', 'regex:/^\d{10}$/'],
            'contents'                   => ['required', 'string', 'max:300'],
            'weightCategory'             => ['required', 'in:Up to 5 kg,5-15 kg'],
            // Pickup location
            'pickup.latitude'            => ['required', 'numeric', 'between:-90,90'],
            'pickup.longitude'           => ['required', 'numeric', 'between:-180,180'],
            'pickup.formattedAddress'    => ['nullable', 'string', 'max:500'],
            // Destination
            'destination.latitude'       => ['required', 'numeric', 'between:-90,90'],
            'destination.longitude'      => ['required', 'numeric', 'between:-180,180'],
            'destination.formattedAddress' => ['nullable', 'string', 'max:500'],
            // Payment
            'paymentMethod'              => ['required', 'in:cash,wallet,upi,card'],
        ], [
            'senderPhone.regex'   => 'Sender phone must be 10 digits.',
            'receiverPhone.regex' => 'Receiver phone must be 10 digits.',
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

        // Parcel pricing: base 60 + 12/km + weight surcharge
        $baseFare      = 60.00;
        $distanceFare  = round($distKm * 12, 2);
        $weightSurcharge = $request->input('weightCategory') === '5-15 kg' ? 50.00 : 0.00;
        $totalFare     = round($baseFare + $distanceFare + $weightSurcharge, 2);
        $durationMins  = (int) max(10, round($distKm * 3));
        $otp           = (string) rand(1000, 9999);

        $parcelDetails = [
            'type'           => $request->input('type', 'send'),
            'senderName'     => $request->input('senderName'),
            'senderPhone'    => $request->input('senderPhone'),
            'receiverName'   => $request->input('receiverName'),
            'receiverPhone'  => $request->input('receiverPhone'),
            'contents'       => $request->input('contents'),
            'weightCategory' => $request->input('weightCategory'),
            'imagePaths'     => $request->input('imagePaths', []),
            'videoPath'      => $request->input('videoPath'),
        ];

        $ride = Ride::create([
            'customer_id'     => $customer->id,
            'vehicle_type'    => 'bike',
            'ride_category'   => 'parcel',
            'pickup_lat'      => $pLat,
            'pickup_lng'      => $pLng,
            'pickup_address'  => $pAddr,
            'dropoff_lat'     => $dLat,
            'dropoff_lng'     => $dLng,
            'dropoff_address' => $dAddr,
            'distance_km'     => round($distKm, 2),
            'duration_mins'   => $durationMins,
            'base_fare'       => $baseFare,
            'distance_fare'   => $distanceFare,
            'time_fare'       => $weightSurcharge,
            'total_fare'      => $totalFare,
            'payment_method'  => $paymentMethod,
            'payment_status'  => 'pending',
            'status'          => 'requested',
            'otp'             => $otp,
            'parcel_details'  => json_encode($parcelDetails),
        ]);

        return $this->successResponse([
            'rideId'         => $ride->ride_number,
            'ride_category'  => 'parcel',
            'status'         => 'requested',
            'otp'            => $otp,
            'estimatedFare'  => $totalFare,
            'currency'       => 'INR',
            'eta'            => rand(5, 12) . ' mins',
            'parcelDetails'  => $parcelDetails,
            'fareBreakup'    => [
                'baseFare'       => $baseFare,
                'distanceFare'   => $distanceFare,
                'weightSurcharge'=> $weightSurcharge,
                'totalFare'      => $totalFare,
            ],
        ], 'Parcel delivery booked. Searching for delivery partners…', 201);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7.2  POST /parcel/upload
    // ──────────────────────────────────────────────────────────────────────────
    public function upload(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $file = $request->file('file') ?? $request->file('photo') ?? $request->file('image') ?? $request->file('media');

        if (!$file) {
            return $this->errorResponse('VALIDATION_ERROR', 'A file or photo is required.', 422);
        }

        $validator = Validator::make(['file' => $file], [
            'file' => ['required', 'file', 'mimes:jpeg,png,jpg,webp,mp4', 'max:51200'], // 50 MB max
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $isVideo  = in_array($file->getClientMimeType(), ['video/mp4']);
        $dir      = $isVideo ? 'parcel/videos' : 'parcel/images';
        $path     = $file->store($dir, 'public');
        $url      = '/storage/' . $path;

        return $this->successResponse([
            'url'       => $url,
            'photo_url' => $url,
            'type'      => $isVideo ? 'video' : 'image',
            'mimeType'  => $file->getClientMimeType(),
            'size'      => $file->getSize(),
        ], 'File uploaded successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────

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
