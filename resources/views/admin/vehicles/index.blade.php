@extends('admin.layouts.app')

@section('title', 'Alo Admin - Vehicle Fleet Management')
@section('page_title', 'Vehicle Fleet Management')

@section('content')

    <!-- Metrics Bar -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">TOTAL FLEET</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-top: 4px;">{{ number_format($totalVehicles) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #10b981; font-weight: 600;">ACTIVE IN SERVICE</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #10b981; margin-top: 4px;">{{ number_format($activeVehicles) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #f59e0b; font-weight: 600;">UNDER MAINTENANCE</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b; margin-top: 4px;">{{ number_format($maintenanceVehicles) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #ef4444; font-weight: 600;">INACTIVE</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #ef4444; margin-top: 4px;">{{ number_format($inactiveVehicles) }}</div>
        </div>
    </div>

    <!-- Filter & Action Header -->
    <div class="card" style="padding: 18px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <form method="GET" action="{{ route('admin.vehicles.index') }}" class="filter-bar" style="margin: 0; flex: 1;">
                <div class="filter-tabs">
                    <a href="{{ route('admin.vehicles.index', ['status' => 'all', 'type' => $type, 'search' => $search]) }}" class="tab-btn {{ $status === 'all' ? 'active' : '' }}">All Status</a>
                    <a href="{{ route('admin.vehicles.index', ['status' => 'active', 'type' => $type, 'search' => $search]) }}" class="tab-btn {{ $status === 'active' ? 'active' : '' }}">Active</a>
                    <a href="{{ route('admin.vehicles.index', ['status' => 'maintenance', 'type' => $type, 'search' => $search]) }}" class="tab-btn {{ $status === 'maintenance' ? 'active' : '' }}">Maintenance</a>
                    <a href="{{ route('admin.vehicles.index', ['status' => 'inactive', 'type' => $type, 'search' => $search]) }}" class="tab-btn {{ $status === 'inactive' ? 'active' : '' }}">Inactive</a>
                </div>

                <div class="filter-tabs">
                    <a href="{{ route('admin.vehicles.index', ['type' => 'all', 'status' => $status, 'search' => $search]) }}" class="tab-btn {{ $type === 'all' ? 'active' : '' }}">All Types</a>
                    <a href="{{ route('admin.vehicles.index', ['type' => 'bike', 'status' => $status, 'search' => $search]) }}" class="tab-btn {{ $type === 'bike' ? 'active' : '' }}">Bike</a>
                    <a href="{{ route('admin.vehicles.index', ['type' => 'auto', 'status' => $status, 'search' => $search]) }}" class="tab-btn {{ $type === 'auto' ? 'active' : '' }}">Auto</a>
                    <a href="{{ route('admin.vehicles.index', ['type' => 'mini', 'status' => $status, 'search' => $search]) }}" class="tab-btn {{ $type === 'mini' ? 'active' : '' }}">Mini</a>
                    <a href="{{ route('admin.vehicles.index', ['type' => 'sedan', 'status' => $status, 'search' => $search]) }}" class="tab-btn {{ $type === 'sedan' ? 'active' : '' }}">Sedan</a>
                    <a href="{{ route('admin.vehicles.index', ['type' => 'suv', 'status' => $status, 'search' => $search]) }}" class="tab-btn {{ $type === 'suv' ? 'active' : '' }}">SUV</a>
                </div>

                <div class="search-box">
                    <svg class="search-icon-pos" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" class="search-input" placeholder="Search plate, owner, or model..." value="{{ $search }}">
                </div>
            </form>
        </div>
    </div>

    <!-- Vehicles Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Registration Plate</th>
                        <th>Type</th>
                        <th>Make & Model</th>
                        <th>Color / Year</th>
                        <th>Owner Name</th>
                        <th>Assigned Driver</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vehicles as $veh)
                        <tr>
                            <td>
                                <strong style="font-family: monospace; font-size: 0.95rem; color: var(--primary);">{{ $veh->reg_number }}</strong>
                            </td>
                            <td>
                                @if($veh->vehicle_type === 'bike')
                                    <span class="badge badge-success">BIKE</span>
                                @elseif($veh->vehicle_type === 'auto')
                                    <span class="badge badge-warning">AUTO</span>
                                @elseif($veh->vehicle_type === 'mini')
                                    <span class="badge badge-info">MINI</span>
                                @elseif($veh->vehicle_type === 'sedan')
                                    <span class="badge badge-neutral">SEDAN</span>
                                @else
                                    <span class="badge badge-neutral">SUV</span>
                                @endif
                            </td>
                            <td>
                                <div style="font-weight: 600;">{{ $veh->make }} {{ $veh->model }}</div>
                            </td>
                            <td>
                                <span style="color: #64748b;">{{ $veh->color ?? 'Standard' }}</span>
                                @if($veh->year)
                                    <span style="font-size: 0.75rem; color: #94a3b8;">({{ $veh->year }})</span>
                                @endif
                            </td>
                            <td>{{ $veh->owner_name }}</td>
                            <td>
                                @if($veh->driver)
                                    <a href="{{ route('admin.drivers.show', $veh->driver->id) }}" style="text-decoration: none; font-weight: 600; color: #0284c7;">
                                        {{ $veh->driver->name }}
                                    </a>
                                @else
                                    <span style="color: #94a3b8; font-style: italic;">Unassigned</span>
                                @endif
                            </td>
                            <td>
                                @if($veh->status === 'active')
                                    <span class="badge badge-success">Active</span>
                                @elseif($veh->status === 'maintenance')
                                    <span class="badge badge-warning">Maintenance</span>
                                @else
                                    <span class="badge badge-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <form action="{{ route('admin.vehicles.status', $veh->id) }}" method="POST" style="margin: 0;">
                                        @csrf
                                        <select name="status" onchange="this.form.submit()" style="padding: 4px 6px; border-radius: 6px; font-size: 0.75rem; border: 1px solid var(--border-color);">
                                            <option value="active" {{ $veh->status === 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="maintenance" {{ $veh->status === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                            <option value="inactive" {{ $veh->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </form>

                                    <form action="{{ route('admin.vehicles.destroy', $veh->id) }}" method="POST" onsubmit="return confirm('Delete this vehicle?')" style="margin: 0;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-secondary btn-sm" style="color: #ef4444; padding: 4px 8px;">&times;</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 48px; color: #94a3b8;">
                                <div style="font-size: 1.1rem; font-weight: 600; color: #64748b;">No vehicles found</div>
                                <p style="font-size: 0.85rem; margin-top: 4px;">Vehicles submitted by drivers through the Driver App will appear here for verification and status management.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($vehicles->hasPages())
            <div style="padding: 16px; border-top: 1px solid var(--border-color);">
                {{ $vehicles->links() }}
            </div>
        @endif
    </div>

@endsection
