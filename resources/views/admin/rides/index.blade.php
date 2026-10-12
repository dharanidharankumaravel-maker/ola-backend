@extends('admin.layouts.app')

@section('title', 'Alo Admin - Ride Management')
@section('page_title', 'Ride Management')

@section('content')

    <!-- Metrics Header -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">TOTAL TRIPS</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-top: 4px;">{{ number_format($totalCount) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #0284c7; font-weight: 600;">ACTIVE / ONGOING</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #0284c7; margin-top: 4px;">{{ number_format($activeCount) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #10b981; font-weight: 600;">COMPLETED</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #10b981; margin-top: 4px;">{{ number_format($completedCount) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #ef4444; font-weight: 600;">CANCELLED</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #ef4444; margin-top: 4px;">{{ number_format($cancelledCount) }}</div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card" style="padding: 18px; margin-bottom: 20px;">
        <form method="GET" action="{{ route('admin.rides.index') }}" class="filter-bar" style="margin: 0;">
            <div class="filter-tabs">
                <a href="{{ route('admin.rides.index', ['status' => 'all', 'search' => $search]) }}" class="tab-btn {{ $status === 'all' ? 'active' : '' }}">All Rides</a>
                <a href="{{ route('admin.rides.index', ['status' => 'active', 'search' => $search]) }}" class="tab-btn {{ $status === 'active' ? 'active' : '' }}">Active</a>
                <a href="{{ route('admin.rides.index', ['status' => 'completed', 'search' => $search]) }}" class="tab-btn {{ $status === 'completed' ? 'active' : '' }}">Completed</a>
                <a href="{{ route('admin.rides.index', ['status' => 'cancelled', 'search' => $search]) }}" class="tab-btn {{ $status === 'cancelled' ? 'active' : '' }}">Cancelled</a>
            </div>

            <div class="search-box">
                <svg class="search-icon-pos" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" class="search-input" placeholder="Search by ride ID, customer, or driver..." value="{{ $search }}">
            </div>
        </form>
    </div>

    <!-- Rides Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ride ID</th>
                        <th>Customer</th>
                        <th>Driver</th>
                        <th>Vehicle Type</th>
                        <th>Pickup & Dropoff</th>
                        <th>Distance / Time</th>
                        <th>Fare</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rides as $ride)
                        <tr>
                            <td>
                                <strong style="color: var(--primary);">#{{ $ride->ride_number }}</strong>
                                <div style="font-size: 0.72rem; color: #94a3b8;">{{ $ride->created_at->format('M d, h:i A') }}</div>
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
                                    <span style="color: #94a3b8; font-style: italic;">Not Assigned</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-neutral">{{ strtoupper($ride->vehicle_type) }}</span>
                            </td>
                            <td>
                                <div style="font-size: 0.8rem; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $ride->pickup_address }}">
                                    <span style="color: #10b981; font-weight: bold;">P:</span> {{ $ride->pickup_address }}
                                </div>
                                <div style="font-size: 0.8rem; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $ride->dropoff_address }}">
                                    <span style="color: #ef4444; font-weight: bold;">D:</span> {{ $ride->dropoff_address }}
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600;">{{ $ride->distance_km }} km</div>
                                <div style="font-size: 0.75rem; color: #94a3b8;">{{ $ride->duration_mins }} mins</div>
                            </td>
                            <td>
                                <strong style="font-size: 0.95rem;">₹{{ number_format($ride->total_fare, 2) }}</strong>
                            </td>
                            <td>
                                <span class="badge {{ $ride->payment_status === 'paid' ? 'badge-success' : 'badge-warning' }}">
                                    {{ strtoupper($ride->payment_method) }} &bull; {{ ucfirst($ride->payment_status) }}
                                </span>
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
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 48px; color: #94a3b8;">
                                <div style="font-size: 1.1rem; font-weight: 600; color: #64748b;">No rides found</div>
                                <p style="font-size: 0.85rem; margin-top: 4px;">Rides requested from the customer app or APIs will appear here.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rides->hasPages())
            <div style="padding: 16px; border-top: 1px solid var(--border-color);">
                {{ $rides->links() }}
            </div>
        @endif
    </div>

@endsection
