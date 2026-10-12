<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $search = $request->query('search');

        $query = Driver::with('vehicle')->latest();

        if ($status !== 'all') {
            if ($status === 'online') {
                $query->where('online_status', 'online');
            } elseif ($status === 'offline') {
                $query->where('online_status', 'offline');
            } elseif ($status === 'pending') {
                $query->where('status', 'pending');
            } elseif ($status === 'blocked') {
                $query->where('status', 'blocked');
            } elseif ($status === 'approved') {
                $query->where('status', 'approved');
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('driver_code', 'like', "%{$search}%");
            });
        }

        $drivers = $query->paginate(15)->withQueryString();

        // Metrics
        $totalDrivers = Driver::count();
        $onlineDrivers = Driver::where('online_status', 'online')->count();
        $pendingDrivers = Driver::where('status', 'pending')->count();
        $blockedDrivers = Driver::where('status', 'blocked')->count();

        return view('admin.drivers.index', compact(
            'drivers',
            'status',
            'search',
            'totalDrivers',
            'onlineDrivers',
            'pendingDrivers',
            'blockedDrivers'
        ));
    }

    public function show($id)
    {
        $driver = Driver::with(['vehicle', 'rides' => function ($q) {
            $q->latest()->take(10);
        }, 'payouts' => function ($q) {
            $q->latest()->take(10);
        }, 'complaints'])->findOrFail($id);

        $vehicles = Vehicle::all();

        return view('admin.drivers.show', compact('driver', 'vehicles'));
    }

    public function update(Request $request, $id)
    {
        $driver = Driver::findOrFail($id);
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:drivers,email,{$id}",
            'phone' => "required|string|unique:drivers,phone,{$id}",
            'license_number' => 'nullable|string|max:50',
            'status' => 'required|in:pending,approved,rejected,blocked',
            'online_status' => 'required|in:online,offline',
            'vehicle_id' => 'nullable|exists:vehicles,id',
        ]);

        $driver->update($request->only([
            'name', 'email', 'phone', 'license_number', 'status', 'online_status', 'vehicle_id'
        ]));

        if ($request->vehicle_id) {
            Vehicle::where('id', $request->vehicle_id)->update(['driver_id' => $driver->id]);
        }

        return back()->with('success', "Driver {$driver->name} details updated.");
    }

    public function approve($id)
    {
        $driver = Driver::findOrFail($id);
        $driver->status = 'approved';
        $driver->documents_verified = true;
        $driver->save();

        return back()->with('success', "Driver {$driver->name} has been approved.");
    }

    public function reject($id)
    {
        $driver = Driver::findOrFail($id);
        $driver->status = 'rejected';
        $driver->save();

        return back()->with('success', "Driver {$driver->name} application rejected.");
    }

    public function toggleBlock($id)
    {
        $driver = Driver::findOrFail($id);
        $driver->status = ($driver->status === 'blocked') ? 'approved' : 'blocked';
        $driver->save();

        $msg = $driver->status === 'blocked' ? 'blocked' : 'unblocked';
        return back()->with('success', "Driver {$driver->name} has been {$msg}.");
    }

    public function destroy($id)
    {
        $driver = Driver::findOrFail($id);
        $name = $driver->name;
        $driver->delete();

        return redirect()->route('admin.drivers.index')->with('success', "Driver {$name} deleted successfully.");
    }
}
