<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\Driver;
use App\Models\Customer;
use Illuminate\Http\Request;

class RideController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $search = $request->query('search');

        $query = Ride::with(['customer', 'driver', 'vehicle'])->latest();

        if ($status !== 'all') {
            if ($status === 'active') {
                $query->whereIn('status', ['requested', 'driver_assigned', 'driver_arrived', 'in_progress']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ride_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%");
                  })
                  ->orWhereHas('driver', function ($dq) use ($search) {
                      $dq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $rides = $query->paginate(15)->withQueryString();

        // Metrics
        $totalCount = Ride::count();
        $activeCount = Ride::whereIn('status', ['requested', 'driver_assigned', 'driver_arrived', 'in_progress'])->count();
        $completedCount = Ride::where('status', 'completed')->count();
        $cancelledCount = Ride::where('status', 'cancelled')->count();

        return view('admin.rides.index', compact(
            'rides',
            'status',
            'search',
            'totalCount',
            'activeCount',
            'completedCount',
            'cancelledCount'
        ));
    }

    public function show($id)
    {
        $ride = Ride::with(['customer', 'driver', 'vehicle', 'payments', 'complaints'])->findOrFail($id);
        return view('admin.rides.show', compact('ride'));
    }

    public function updateStatus(Request $request, $id)
    {
        $ride = Ride::findOrFail($id);
        $request->validate([
            'status' => 'required|in:requested,driver_assigned,driver_arrived,in_progress,completed,cancelled',
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        $ride->status = $request->status;
        if ($request->status === 'cancelled') {
            $ride->cancel_reason = $request->cancel_reason ?? 'Cancelled by Admin';
            $ride->cancelled_by = 'admin';
            $ride->cancelled_at = now();
        } elseif ($request->status === 'completed') {
            $ride->completed_at = now();
            $ride->payment_status = 'paid';
        }

        $ride->save();

        return back()->with('success', "Ride #{$ride->ride_number} status updated to {$ride->status}.");
    }
}
