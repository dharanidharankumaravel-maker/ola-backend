<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Customer;
use App\Models\Ride;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        // 4 Core Stat Cards
        $totalDrivers = Driver::count();
        $totalCustomers = Customer::count();
        $activeRides = Ride::whereIn('status', ['requested', 'driver_assigned', 'driver_arrived', 'in_progress'])->count();
        $revenueToday = Ride::whereDate('completed_at', $today)->where('status', 'completed')->sum('total_fare');

        // Recent Rides
        $recentRides = Ride::with(['customer', 'driver', 'vehicle'])
            ->latest()
            ->take(6)
            ->get();

        // Live Active Drivers for map
        $activeDrivers = Driver::with('vehicle')
            ->where('online_status', 'online')
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->take(20)
            ->get();

        // Weekly revenue data for chart
        $revenueChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayName = $date->format('D');
            $dailyTotal = Ride::whereDate('completed_at', $date)->where('status', 'completed')->sum('total_fare');
            $revenueChart[] = [
                'day' => $dayName,
                'revenue' => (float)$dailyTotal,
            ];
        }

        return view('admin.dashboard', compact(
            'totalDrivers',
            'totalCustomers',
            'activeRides',
            'revenueToday',
            'recentRides',
            'activeDrivers',
            'revenueChart'
        ));
    }
}
