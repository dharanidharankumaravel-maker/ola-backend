<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Ride;
use App\Models\SosEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Module 9:  Ratings & Feedback
 * Module 10: Safety & Emergency (SOS)
 * Module 11: Support & FAQs
 * Module 12: Push Notification Device Registration
 */
class CustomerSupportController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // 9.1  POST /rides/:id/feedback
    // ──────────────────────────────────────────────────────────────────────────
    public function submitFeedback(Request $request, string $id): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $validator = Validator::make($request->all(), [
            'rating'  => ['required', 'numeric', 'min:1', 'max:5'],
            'review'  => ['nullable', 'string', 'max:500'],
            'tags'    => ['nullable', 'array'],
            'tags.*'  => ['string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $ride = Ride::where('customer_id', $customer->id)
            ->where(fn($q) => $q->where('id', $id)->orWhere('ride_number', $id))
            ->where('status', 'completed')
            ->first();

        if (!$ride) {
            return $this->errorResponse('NOT_FOUND', 'Completed ride not found.', 404);
        }

        if ($ride->driver_rating !== null) {
            return $this->errorResponse('ALREADY_RATED', 'Feedback already submitted for this ride.', 400);
        }

        $rating  = (float) $request->input('rating');
        $review  = $request->input('review');
        $tags    = $request->input('tags', []);

        $ride->update([
            'driver_rating' => $rating,
            'driver_review' => $review ? ($review . (!empty($tags) ? ' [' . implode(', ', $tags) . ']' : '')) : null,
        ]);

        // Update driver's average rating
        if ($ride->driver) {
            $driver      = $ride->driver;
            $ratingCount = Ride::where('driver_id', $driver->id)->whereNotNull('driver_rating')->count();
            $avgRating   = Ride::where('driver_id', $driver->id)->whereNotNull('driver_rating')->avg('driver_rating');
            $driver->update(['rating' => round($avgRating, 2)]);
        }

        return $this->successResponse([
            'rideId'  => $ride->ride_number,
            'rating'  => $rating,
            'review'  => $review,
            'message' => 'Thank you for your feedback!',
        ], 'Feedback submitted successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10.1  POST /safety/sos
    // ──────────────────────────────────────────────────────────────────────────
    public function triggerSos(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $lat = $request->input('latitude') ?? $request->input('lat');
        $lng = $request->input('longitude') ?? $request->input('lng');
        $rideIdInput = $request->input('rideId') ?? $request->input('ride_id');

        $validator = Validator::make([
            'rideId' => $rideIdInput,
            'latitude' => $lat,
            'longitude' => $lng,
        ], [
            'rideId'    => ['nullable'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $rideId = null;
        if (!empty($rideIdInput)) {
            $ride = Ride::where('customer_id', $customer->id)
                ->where(fn($q) => $q->where('id', $rideIdInput)->orWhere('ride_number', $rideIdInput))
                ->first();
            $rideId = $ride?->id;
        }

        $sos = SosEvent::create([
            'customer_id'       => $customer->id,
            'ride_id'           => $rideId,
            'latitude'          => $lat,
            'longitude'         => $lng,
            'emergency_contact' => $customer->emergency_contact,
            'status'            => 'triggered',
            'triggered_at'      => now(),
        ]);

        // In production: dispatch FCM push to safety team, notify emergency contact via SMS
        $caseNumber = 'SOS-' . strtoupper(Str::random(8));

        return $this->successResponse([
            'sosId'             => $sos->id,
            'caseNumber'        => $caseNumber,
            'status'            => 'triggered',
            'emergencyContact'  => $customer->emergency_contact,
            'message'           => 'SOS alert dispatched to safety team. Help is on the way.',
            'triggeredAt'       => $sos->triggered_at->format('Y-m-d\TH:i:s\Z'),
        ], 'SOS triggered successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10.2  POST /rides/:id/share
    // ──────────────────────────────────────────────────────────────────────────
    public function shareRide(Request $request, string $id): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $ride = Ride::where('customer_id', $customer->id)
            ->where(fn($q) => $q->where('id', $id)->orWhere('ride_number', $id))
            ->whereIn('status', ['driver_assigned', 'driver_arrived', 'in_progress'])
            ->first();

        if (!$ride) {
            return $this->errorResponse('NOT_FOUND', 'Active ride not found.', 404);
        }

        // Generate or reuse share token
        if (!$ride->share_token) {
            $ride->share_token = Str::random(32);
            $ride->save();
        }

        $shareUrl = url('/track/' . $ride->share_token);

        return $this->successResponse([
            'shareUrl'     => $shareUrl,
            'tracking_url' => $shareUrl,
            'token'        => $ride->share_token,
            'expiresIn'    => 'Until ride completion',
            'rideId'       => $ride->ride_number,
        ], 'Live share link generated');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 11.1  GET /support/faqs
    // ──────────────────────────────────────────────────────────────────────────
    public function getFaqs(Request $request): JsonResponse
    {
        $faqs = [
            [
                'category' => 'Lost Items',
                'articles' => [
                    ['id' => 1, 'question' => 'How do I report a lost item?', 'answer' => 'Go to Ride History, select the trip, and tap "Report Lost Item". We will connect you with your driver within 30 minutes.'],
                    ['id' => 2, 'question' => 'How long does item recovery take?', 'answer' => 'Most items are recovered within 2–4 hours if the driver is still on shift.'],
                ],
            ],
            [
                'category' => 'Billing & Payments',
                'articles' => [
                    ['id' => 3, 'question' => 'Why was I charged a cancellation fee?', 'answer' => 'A cancellation fee of ₹50 applies if you cancel after the driver has arrived at your pickup location.'],
                    ['id' => 4, 'question' => 'How do I get a fare adjustment?', 'answer' => 'Raise a support ticket with your ride ID. Our team will review and process within 24 hours.'],
                    ['id' => 5, 'question' => 'When is my wallet refund processed?', 'answer' => 'Wallet refunds are processed instantly. Bank refunds take 5–7 business days.'],
                ],
            ],
            [
                'category' => 'Driver Behavior',
                'articles' => [
                    ['id' => 6, 'question' => 'How do I report a driver?', 'answer' => 'Use the feedback section after your trip to rate and report. You can also submit a support ticket.'],
                    ['id' => 7, 'question' => 'What happens after I report a driver?', 'answer' => 'Our safety team reviews all reports within 24 hours. Serious violations result in driver suspension.'],
                ],
            ],
            [
                'category' => 'Account & Privacy',
                'articles' => [
                    ['id' => 8, 'question' => 'How do I delete my account?', 'answer' => 'Go to Profile → Settings → Delete Account. This action is permanent and cannot be undone.'],
                    ['id' => 9, 'question' => 'Who can see my location?', 'answer' => 'Your location is only shared with your assigned driver during an active trip.'],
                ],
            ],
        ];

        return $this->successResponse(['categories' => $faqs], 'FAQs retrieved');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 11.2  POST /support/tickets
    // ──────────────────────────────────────────────────────────────────────────
    public function createTicket(Request $request): JsonResponse
    {
        if ($request->attributes->get('driver')) {
            return app(\App\Http\Controllers\Driver\SupportController::class)->submitTicket($request);
        }

        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $validator = Validator::make($request->all(), [
            'subject'    => ['required', 'string', 'max:200'],
            'category'   => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:2000'],
            'rideId'     => ['nullable', 'string'],
            'priority'   => ['nullable', 'in:low,medium,high'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $rideId = null;
        if ($request->filled('rideId')) {
            $ride   = Ride::where('customer_id', $customer->id)
                ->where(fn($q) => $q->where('id', $request->input('rideId'))->orWhere('ride_number', $request->input('rideId')))
                ->first();
            $rideId = $ride?->id;
        }

        $ticketNumber = 'TKT-' . strtoupper(Str::random(8));

        $ticket = Complaint::create([
            'ticket_number' => $ticketNumber,
            'customer_id'   => $customer->id,
            'ride_id'       => $rideId,
            'subject'       => $request->input('subject'),
            'category'      => $request->input('category'),
            'description'   => $request->input('description'),
            'priority'      => $request->input('priority', 'medium'),
            'status'        => 'open',
        ]);

        return $this->successResponse([
            'ticketId'     => $ticket->id,
            'ticketNumber' => $ticketNumber,
            'status'       => 'open',
            'createdAt'    => $ticket->created_at->format('Y-m-d\TH:i:s\Z'),
        ], 'Support ticket created', 201);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 12.1  POST /notifications/device
    // ──────────────────────────────────────────────────────────────────────────
    public function registerDevice(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $fcmToken = $request->input('fcmToken') ?? $request->input('fcm_token');
        $deviceId = $request->input('deviceId') ?? $request->input('device_id');
        $platform = $request->input('platform');

        $validator = Validator::make([
            'fcmToken' => $fcmToken,
            'platform' => $platform,
            'deviceId' => $deviceId,
        ], [
            'fcmToken' => ['required', 'string', 'min:10'],
            'platform' => ['required', 'in:android,ios,web'],
            'deviceId' => ['nullable', 'string', 'max:200'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        // Update FCM key on users table for legacy compatibility
        if ($customer->user) {
            $customer->user->update(['fcm_key' => $fcmToken]);
        }

        // Upsert device token record
        DB::table('customer_device_tokens')->updateOrInsert(
            [
                'customer_id' => $customer->id,
                'device_id'   => $deviceId,
            ],
            [
                'fcm_token'  => $fcmToken,
                'platform'   => $platform,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return $this->successResponse([
            'registered' => true,
            'platform'   => $request->input('platform'),
        ], 'Device registered for push notifications');
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
