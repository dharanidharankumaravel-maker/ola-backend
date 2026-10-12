<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\Driver;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $type = $request->query('type', 'all');
        $search = $request->query('search');

        $query = Vehicle::with('driver')->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($type !== 'all') {
            $query->where('vehicle_type', $type);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reg_number', 'like', "%{$search}%")
                  ->orWhere('owner_name', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhere('make', 'like', "%{$search}%");
            });
        }

        $vehicles = $query->paginate(15)->withQueryString();

        $totalVehicles = Vehicle::count();
        $activeVehicles = Vehicle::where('status', 'active')->count();
        $maintenanceVehicles = Vehicle::where('status', 'maintenance')->count();
        $inactiveVehicles = Vehicle::where('status', 'inactive')->count();

        $drivers = Driver::all();

        return view('admin.vehicles.index', compact(
            'vehicles',
            'status',
            'type',
            'search',
            'totalVehicles',
            'activeVehicles',
            'maintenanceVehicles',
            'inactiveVehicles',
            'drivers'
        ));
    }

    public function update(Request $request, $id)
    {
        $vehicle = Vehicle::findOrFail($id);
        $request->validate([
            'reg_number' => "required|string|unique:vehicles,reg_number,{$id}|max:20",
            'vehicle_type' => 'required|in:bike,auto,mini,sedan,suv',
            'make' => 'required|string|max:50',
            'model' => 'required|string|max:50',
            'owner_name' => 'required|string|max:100',
            'status' => 'required|in:active,maintenance,inactive',
            'driver_id' => 'nullable|exists:drivers,id',
        ]);

        $vehicle->update($request->all());

        if ($request->driver_id) {
            Driver::where('id', $request->driver_id)->update(['vehicle_id' => $vehicle->id]);
        }

        return back()->with('success', "Vehicle {$vehicle->reg_number} updated.");
    }

    public function updateStatus(Request $request, $id)
    {
        $vehicle = Vehicle::findOrFail($id);
        $request->validate(['status' => 'required|in:active,maintenance,inactive']);
        $vehicle->status = $request->status;
        $vehicle->save();

        return back()->with('success', "Vehicle status changed to {$vehicle->status}.");
    }

    public function destroy($id)
    {
        $vehicle = Vehicle::findOrFail($id);
        $plate = $vehicle->reg_number;
        $vehicle->delete();

        return redirect()->route('admin.vehicles.index')->with('success', "Vehicle {$plate} deleted.");
    }
}
