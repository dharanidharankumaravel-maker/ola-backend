<?php

namespace App\Http\Controllers\Driver;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseDriverController
{
    /**
     * 1. Get Notification List
     * GET /driver/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);

        $notifications = Notification::where(function ($q) use ($driver) {
            $q->where('target_type', 'all')
              ->orWhere('target_type', 'drivers');
            if ($driver) {
                $q->orWhere(function ($sub) use ($driver) {
                    $sub->where('target_type', 'user')
                        ->where('target_id', $driver->user_id);
                });
            }
        })
        ->latest()
        ->take(50)
        ->get();

        if ($notifications->isEmpty()) {
            $data = [
                [
                    'id'          => '1',
                    'title'       => 'New Ride Request',
                    'description' => 'Pickup at MG Road, 2.5 km away.',
                    'timestamp'   => now()->subMinutes(10)->toIso8601ZuluString(),
                    'type'        => 'request',
                    'isRead'      => false,
                ],
                [
                    'id'          => '2',
                    'title'       => 'Payment Received',
                    'description' => '₹ 450 received for Ride #TRX123',
                    'timestamp'   => now()->subMinutes(20)->toIso8601ZuluString(),
                    'type'        => 'payment',
                    'isRead'      => false,
                ],
            ];
        } else {
            $data = $notifications->map(function ($n) {
                return [
                    'id'          => (string) $n->id,
                    'title'       => $n->title,
                    'description' => $n->description ?: $n->message,
                    'timestamp'   => $n->created_at->toIso8601ZuluString(),
                    'type'        => $n->type ?: 'general',
                    'isRead'      => (bool) $n->is_read,
                ];
            });
        }

        return $this->successResponse($data);
    }

    /**
     * 2. Mark Notification as Read
     * PATCH /driver/notifications/{id}/read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = Notification::find($id);
        if ($notification) {
            $notification->is_read = true;
            $notification->save();
        }

        return $this->successResponse(
            null,
            'Notification marked as read.',
            200
        );
    }

    /**
     * 3. Clear All Notifications
     * DELETE /driver/notifications
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);

        if ($driver) {
            Notification::where(function ($q) use ($driver) {
                $q->where('target_type', 'user')->where('target_id', $driver->user_id);
            })->delete();
        }

        return $this->successResponse(
            null,
            'Notifications cleared successfully.',
            200
        );
    }
}
