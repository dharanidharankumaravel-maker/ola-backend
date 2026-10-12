@extends('admin.layouts.app')

@section('title', 'Alo Admin - Driver Management')
@section('page_title', 'Driver Management')

@section('content')

    <!-- Metrics Bar -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">TOTAL DRIVERS</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-top: 4px;">{{ number_format($totalDrivers) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #10b981; font-weight: 600;">ONLINE NOW</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #10b981; margin-top: 4px;">{{ number_format($onlineDrivers) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #f59e0b; font-weight: 600;">PENDING APPROVAL</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b; margin-top: 4px;">{{ number_format($pendingDrivers) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #ef4444; font-weight: 600;">BLOCKED</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #ef4444; margin-top: 4px;">{{ number_format($blockedDrivers) }}</div>
        </div>
    </div>

    <!-- Filter & Action Header -->
    <div class="card" style="padding: 18px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <form method="GET" action="{{ route('admin.drivers.index') }}" class="filter-bar" style="margin: 0; flex: 1;">
                <div class="filter-tabs">
                    <a href="{{ route('admin.drivers.index', ['status' => 'all', 'search' => $search]) }}" class="tab-btn {{ $status === 'all' ? 'active' : '' }}">All</a>
                    <a href="{{ route('admin.drivers.index', ['status' => 'online', 'search' => $search]) }}" class="tab-btn {{ $status === 'online' ? 'active' : '' }}">Online</a>
                    <a href="{{ route('admin.drivers.index', ['status' => 'pending', 'search' => $search]) }}" class="tab-btn {{ $status === 'pending' ? 'active' : '' }}">Pending</a>
                    <a href="{{ route('admin.drivers.index', ['status' => 'approved', 'search' => $search]) }}" class="tab-btn {{ $status === 'approved' ? 'active' : '' }}">Approved</a>
                    <a href="{{ route('admin.drivers.index', ['status' => 'blocked', 'search' => $search]) }}" class="tab-btn {{ $status === 'blocked' ? 'active' : '' }}">Blocked</a>
                </div>

                <div class="search-box">
                    <svg class="search-icon-pos" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" class="search-input" placeholder="Search by driver name, phone, vehicle, or ID..." value="{{ $search }}">
                </div>
            </form>
        </div>
    </div>

    <!-- Drivers Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Driver ID</th>
                        <th>Name & Contact</th>
                        <th>Vehicle & Plate</th>
                        <th>Rating</th>
                        <th>Trips</th>
                        <th>Total Earnings</th>
                        <th>Status</th>
                        <th>Online</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($drivers as $driver)
                        <tr>
                            <td>
                                <strong style="color: var(--primary);">#{{ $driver->driver_code }}</strong>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="admin-avatar" style="width: 36px; height: 36px; font-size: 0.85rem; background: #e0f2fe; color: #0284c7;">
                                        {{ substr($driver->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;">{{ $driver->name }}</div>
                                        <div style="font-size: 0.75rem; color: #94a3b8;">{{ $driver->phone }} &bull; {{ $driver->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($driver->vehicle)
                                    <div style="font-weight: 600; font-family: monospace;">{{ $driver->vehicle->reg_number }}</div>
                                    <div style="font-size: 0.75rem; color: #64748b;">{{ $driver->vehicle->make }} {{ $driver->vehicle->model }} ({{ strtoupper($driver->vehicle->vehicle_type) }})</div>
                                @else
                                    <span style="color: #94a3b8; font-style: italic;">No vehicle</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #d97706;">★ {{ $driver->rating }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 600;">{{ $driver->total_trips }}</div>
                            </td>
                            <td>
                                <strong style="color: #0f172a;">₹{{ number_format($driver->total_earnings, 2) }}</strong>
                            </td>
                            <td>
                                @if($driver->status === 'approved')
                                    <span class="badge badge-success">Approved</span>
                                @elseif($driver->status === 'pending')
                                    <span class="badge badge-warning">Pending</span>
                                @elseif($driver->status === 'rejected')
                                    <span class="badge badge-danger">Rejected</span>
                                @elseif($driver->status === 'blocked')
                                    <span class="badge badge-danger">Blocked</span>
                                @endif
                            </td>
                            <td>
                                @if($driver->online_status === 'online')
                                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.78rem; font-weight: 600; color: #10b981;">
                                        <span class="pulse-dot"></span> Online
                                    </span>
                                @else
                                    <span style="font-size: 0.78rem; color: #94a3b8;">Offline</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <a href="{{ route('admin.drivers.show', $driver->id) }}" class="btn btn-secondary btn-sm">View</a>
                                    @if($driver->status === 'pending')
                                        <form action="{{ route('admin.drivers.approve', $driver->id) }}" method="POST" style="margin: 0;">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 48px; color: #94a3b8;">
                                <div style="font-size: 1.1rem; font-weight: 600; color: #64748b;">No drivers found</div>
                                <p style="font-size: 0.85rem; margin-top: 4px;">Drivers who register through the Driver App will appear here for verification and approval.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($drivers->hasPages())
            <div style="padding: 16px; border-top: 1px solid var(--border-color);">
                {{ $drivers->links() }}
            </div>
        @endif
    </div>

@endsection
