<?php

namespace App\Http\Controllers\Driver;

use App\Models\Complaint;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupportController extends BaseDriverController
{
    /**
     * 1. Submit Support Ticket
     * POST /support/tickets
     */
    public function submitTicket(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);

        $validator = Validator::make($request->all(), [
            'subject'     => 'required|string|max:255',
            'category'    => 'required|string|max:100',
            'description' => 'required|string',
            'rideId'      => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $rideIdParam = $request->input('rideId');
        $ride = null;
        if ($rideIdParam) {
            $ride = Ride::where('id', $rideIdParam)
                ->orWhere('ride_number', $rideIdParam)
                ->first();
        }

        $ticketNumber = 'TCK-' . rand(800000, 899999);

        $complaint = Complaint::create([
            'ticket_number' => $ticketNumber,
            'driver_id'     => $driver?->id,
            'customer_id'   => $ride?->customer_id,
            'ride_id'       => $ride?->id,
            'subject'       => $request->input('subject'),
            'category'      => $request->input('category'),
            'description'   => $request->input('description'),
            'priority'      => 'medium',
            'status'        => 'open',
        ]);

        return $this->successResponse([
            'ticketId'  => $ticketNumber,
            'status'    => 'OPEN',
            'createdAt' => $complaint->created_at->toIso8601ZuluString(),
        ], 'Operation completed successfully.', 201);
    }

    /**
     * 2. Trigger Emergency SOS
     * POST /driver/safety/sos
     */
    public function triggerSos(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);

        $lat = $request->input('currentLat');
        $lng = $request->input('currentLng');
        $activeRideId = $request->input('activeRideId');
        $battery = $request->input('batteryLevel');

        if ($driver && $lat && $lng) {
            $driver->current_lat = $lat;
            $driver->current_lng = $lng;
            $driver->save();
        }

        // Also create critical complaint ticket for tracking emergency in admin panel
        $ride = null;
        if ($activeRideId) {
            $ride = Ride::where('id', $activeRideId)->orWhere('ride_number', $activeRideId)->first();
        }

        Complaint::create([
            'ticket_number' => 'SOS-' . rand(100000, 999999),
            'driver_id'     => $driver?->id,
            'ride_id'       => $ride?->id,
            'subject'       => 'EMERGENCY SOS TRIGGERED',
            'category'      => 'safety',
            'description'   => "Driver triggered Emergency SOS at Coordinates: ({$lat}, {$lng}), Battery Level: {$battery}%, Ride: {$activeRideId}",
            'priority'      => 'critical',
            'status'        => 'open',
        ]);

        return response()->json([
            'success'         => true,
            'statusCode'      => 200,
            'message'         => 'Emergency SOS triggered. Safety dispatch alerted.',
            'dispatchContact' => '+91112',
        ], 200);
    }
}
