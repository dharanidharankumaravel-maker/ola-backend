@extends('admin.layouts.app')

@section('title', 'Alo Admin - Operations Dashboard')
@section('page_title', 'Operations Dashboard')

@section('styles')
<style>
    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }

    .metric-card {
        background: #ffffff;
        border-radius: 14px;
        padding: 20px 22px;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    .metric-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .metric-label {
        font-size: 0.82rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .metric-icon-box {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .metric-value {
        font-size: 1.85rem;
        font-weight: 700;
        color: #0f172a;
        font-family: 'Outfit', sans-serif;
        letter-spacing: -0.03em;
        line-height: 1.1;
    }

    .metric-trend {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
        font-size: 0.8rem;
    }

    .trend-up {
        color: #10b981;
        font-weight: 600;
    }

    .trend-sub {
        color: #94a3b8;
    }

    /* Map & Analytics Grid */
    .dashboard-main-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }

    @media (max-width: 1100px) {
        .dashboard-main-grid {
            grid-template-columns: 1fr;
        }
    }

    .map-container {
        position: relative;
        height: 380px;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        background-color: #0f172a;
    }

    .map-bg-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0.9;
    }

    .map-overlay-badge {
        position: absolute;
        top: 14px;
        left: 14px;
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: blur(8px);
        color: #ffffff;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        z-index: 10;
    }

    /* Live Animated Map Pins */
    .map-pin {
        position: absolute;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #0284c7;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 14px rgba(2, 132, 199, 0.6);
        cursor: pointer;
        transition: transform 0.2s;
        z-index: 5;
    }

    .map-pin:hover {
        transform: scale(1.2);
        z-index: 15;
    }

    .map-pin::after {
        content: '';
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        border: 2px solid #38bdf8;
        animation: pulse-ring 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
    }

    @keyframes pulse-ring {
        0% { transform: scale(0.9); opacity: 0.8; }
        80% { transform: scale(2.2); opacity: 0; }
        100% { transform: scale(2.2); opacity: 0; }
    }

    .pin-bike { background: #10b981; }
    .pin-auto { background: #f59e0b; }
    .pin-suv { background: #6366f1; }

    .quick-actions-bar {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }
</style>
@endsection

@section('content')

    <!-- Quick Action Bar -->
    <div class="quick-actions-bar">
        <a href="{{ route('admin.drivers.index', ['status' => 'pending']) }}" class="btn btn-primary btn-sm">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Review Pending Drivers
        </a>
        <a href="{{ route('admin.vehicles.index') }}" class="btn btn-secondary btn-sm">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            Fleet Vehicles
        </a>
        <a href="{{ route('admin.promotions.index') }}" class="btn btn-secondary btn-sm">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            Add Promo Code
        </a>
        <a href="{{ route('admin.financials.index') }}" class="btn btn-secondary btn-sm">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            View Financials
        </a>
    </div>

    <!-- 4 Core Metrics Cards (Alo Design) -->
    <div class="metrics-grid">
        <!-- Total Drivers -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-label">Total Drivers</span>
                <div class="metric-icon-box" style="background: #e0f2fe; color: #0284c7;">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ number_format($totalDrivers) }}</div>
            <div class="metric-trend">
                <span class="trend-up">&uarr; +12.5%</span>
                <span class="trend-sub">vs last month</span>
            </div>
        </div>

        <!-- Total Customers -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-label">Total Customers</span>
                <div class="metric-icon-box" style="background: #fdf2f8; color: #db2777;">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ number_format($totalCustomers) }}</div>
            <div class="metric-trend">
                <span class="trend-up">&uarr; +8.2%</span>
                <span class="trend-sub">vs last month</span>
            </div>
        </div>

        <!-- Active Rides -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-label">Active Rides</span>
                <div class="metric-icon-box" style="background: #ecfdf5; color: #10b981;">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ number_format($activeRides) }}</div>
            <div class="metric-trend">
                <span class="trend-up">&uarr; +24.1%</span>
                <span class="trend-sub">vs last month</span>
            </div>
        </div>

        <!-- Revenue Today -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-label">Revenue Today</span>
                <div class="metric-icon-box" style="background: #fffbeb; color: #d97706;">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="metric-value">₹{{ number_format($revenueToday, 2) }}</div>
            <div class="metric-trend">
                <span class="trend-up">&uarr; +15.3%</span>
                <span class="trend-sub">vs last month</span>
            </div>
        </div>
    </div>

    <!-- Live Fleet Map & 7-Day Revenue Analytics Grid -->
    <div class="dashboard-main-grid">
        <!-- Live Fleet Map View -->
        <div class="card" style="padding: 18px;">
            <div class="card-header" style="margin-bottom: 14px;">
                <div>
                    <h3 class="card-title">Live Fleet Activity</h3>
                    <p class="card-subtitle">Real-time driver locations and live trip tracking</p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <span class="badge badge-success">Live GPS Feed</span>
                </div>
            </div>

            <div class="map-container">
                <div class="map-overlay-badge">
                    <span class="pulse-dot"></span>
                    <span>Monitoring Active Drivers</span>
                </div>

                <img src="{{ asset('map_demo.png') }}" alt="Live Fleet Map" class="map-bg-img">

                <!-- Interactive Animated Driver Pins -->
                <div class="map-pin pin-bike" style="top: 35%; left: 25%;" title="Bike Driver: Mohan (Online)">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M15.5 5.5c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zM5 12c-2.8 0-5 2.2-5 5s2.2 5 5 5 5-2.2 5-5-2.2-5-5-5zm0 8.5c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5zm14-8.5c-2.8 0-5 2.2-5 5s2.2 5 5 5 5-2.2 5-5-2.2-5-5-5zm0 8.5c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5z"/></svg>
                </div>

                <div class="map-pin pin-auto" style="top: 55%; left: 45%;" title="Auto Driver: Suresh (On Trip)">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.85 7h10.29l1.08 3.11H5.77L6.85 7zM19 17H5v-4.66l.12-.34h13.77l.11.34V17z"/></svg>
                </div>

                <div class="map-pin" style="top: 28%; left: 68%;" title="Sedan Driver: Rajesh (Online)">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.85 7h10.29l1.08 3.11H5.77L6.85 7z"/></svg>
                </div>

                <div class="map-pin pin-suv" style="top: 65%; left: 78%;" title="SUV Driver: Anand (Available)">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/></svg>
                </div>
            </div>
        </div>

        <!-- Revenue Analytics Chart -->
        <div class="card" style="padding: 18px;">
            <div class="card-header" style="margin-bottom: 14px;">
                <div>
                    <h3 class="card-title">Weekly Revenue</h3>
                    <p class="card-subtitle">Last 7 days performance</p>
                </div>
                <span class="badge badge-info">INR (₹)</span>
            </div>

            <div style="height: 310px; position: relative;">
                <canvas id="revenueAnalyticsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Rides / Active Trips Table -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Recent Trips & Live Activity</h3>
                <p class="card-subtitle">Real-time ride requests and completed bookings</p>
            </div>
            <a href="{{ route('admin.rides.index') }}" class="btn btn-secondary btn-sm">
                View All Trips &rarr;
            </a>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ride ID</th>
                        <th>Customer</th>
                        <th>Driver</th>
                        <th>Route</th>
                        <th>Vehicle</th>
                        <th>Fare</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentRides as $ride)
                        <tr>
                            <td>
                                <strong style="color: var(--primary);">#{{ $ride->ride_number }}</strong>
                            </td>
                            <td>
                                <div style="font-weight: 600;">{{ $ride->customer->name ?? 'Customer' }}</div>
                                <div style="font-size: 0.75rem; color: #94a3b8;">{{ $ride->customer->phone ?? '' }}</div>
                            </td>
                            <td>
                                @if($ride->driver)
                                    <div style="font-weight: 600;">{{ $ride->driver->name }}</div>
                                    <div style="font-size: 0.75rem; color: #94a3b8;">{{ $ride->driver->phone }}</div>
                                @else
                                    <span style="color: #94a3b8; font-style: italic;">Unassigned</span>
                                @endif
                            </td>
                            <td>
                                <div style="font-size: 0.8rem; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $ride->pickup_address }}">
                                    <span style="color: #10b981;">&bull;</span> {{ $ride->pickup_address }}
                                </div>
                                <div style="font-size: 0.8rem; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $ride->dropoff_address }}">
                                    <span style="color: #ef4444;">&bull;</span> {{ $ride->dropoff_address }}
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-neutral">{{ strtoupper($ride->vehicle_type) }}</span>
                            </td>
                            <td>
                                <strong style="font-size: 0.95rem;">₹{{ number_format($ride->total_fare, 2) }}</strong>
                            </td>
                            <td>
                                @if($ride->status === 'completed')
                                    <span class="badge badge-success">Completed</span>
                                @elseif(in_array($ride->status, ['in_progress', 'driver_assigned', 'driver_arrived', 'requested']))
                                    <span class="badge badge-info">{{ ucwords(str_replace('_', ' ', $ride->status)) }}</span>
                                @elseif($ride->status === 'cancelled')
                                    <span class="badge badge-danger">Cancelled</span>
                                @else
                                    <span class="badge badge-neutral">{{ $ride->status }}</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.rides.show', $ride->id) }}" class="btn btn-secondary btn-sm">
                                    Details
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">
                                <div style="font-size: 1.1rem; font-weight: 600; color: #64748b; margin-bottom: 6px;">No rides recorded yet</div>
                                <p style="font-size: 0.85rem;">When customer rides are requested through the customer app or API, they will stream here live.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('revenueAnalyticsChart').getContext('2d');
        const chartData = @json($revenueChart);

        const labels = chartData.map(item => item.day);
        const dataValues = chartData.map(item => item.revenue);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (₹)',
                    data: dataValues,
                    borderColor: '#0284c7',
                    backgroundColor: 'rgba(2, 132, 199, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#0284c7',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ₹' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9'
                        },
                        ticks: {
                            callback: function(value) {
                                return '₹' + value;
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    });
</script>
@endsection
