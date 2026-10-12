@extends('admin.layouts.app')

@section('title', "Alo Admin - Ride Details #{$ride->ride_number}")
@section('page_title', "Ride Details: #{$ride->ride_number}")

@section('content')

    <div style="margin-bottom: 20px;">
        <a href="{{ route('admin.rides.index') }}" class="btn btn-secondary btn-sm">&larr; Back to Rides</a>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <!-- Left: Trip breakdown, Route, Fare -->
        <div>
            <!-- Status and Action Card -->
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Ride #{{ $ride->ride_number }}</h3>
                        <p class="card-subtitle">Booked on {{ $ride->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                    <div>
                        @if($ride->status === 'completed')
                            <span class="badge badge-success" style="font-size: 0.9rem; padding: 6px 12px;">Completed</span>
                        @elseif(in_array($ride->status, ['in_progress', 'driver_assigned', 'driver_arrived', 'requested']))
                            <span class="badge badge-info" style="font-size: 0.9rem; padding: 6px 12px;">{{ ucwords(str_replace('_', ' ', $ride->status)) }}</span>
                        @elseif($ride->status === 'cancelled')
                            <span class="badge badge-danger" style="font-size: 0.9rem; padding: 6px 12px;">Cancelled</span>
                        @endif
                    </div>
                </div>

                <!-- Locations -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-bottom: 20px;">
                    <div style="display: flex; gap: 14px; margin-bottom: 14px;">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #10b981; margin-top: 5px; flex-shrink: 0;"></div>
                        <div>
                            <div style="font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Pickup Location</div>
                            <div style="font-weight: 600; color: #0f172a; margin-top: 2px;">{{ $ride->pickup_address }}</div>
                            <div style="font-size: 0.75rem; color: #94a3b8;">Lat: {{ $ride->pickup_lat }}, Lng: {{ $ride->pickup_lng }}</div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 14px;">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #ef4444; margin-top: 5px; flex-shrink: 0;"></div>
                        <div>
                            <div style="font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Dropoff Destination</div>
                            <div style="font-weight: 600; color: #0f172a; margin-top: 2px;">{{ $ride->dropoff_address }}</div>
                            <div style="font-size: 0.75rem; color: #94a3b8;">Lat: {{ $ride->dropoff_lat }}, Lng: {{ $ride->dropoff_lng }}</div>
                        </div>
                    </div>
                </div>

                <!-- Fare Breakdown -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-bottom: 14px;">Fare & Financial Breakdown</h4>
                <div style="border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
                    <table class="data-table" style="margin: 0;">
                        <tbody>
                            <tr>
                                <td>Base Fare</td>
                                <td style="text-align: right; font-weight: 600;">₹{{ number_format($ride->base_fare, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Distance Fare ({{ $ride->distance_km }} km)</td>
                                <td style="text-align: right; font-weight: 600;">₹{{ number_format($ride->distance_fare, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Time / Waiting Fare ({{ $ride->duration_mins }} mins)</td>
                                <td style="text-align: right; font-weight: 600;">₹{{ number_format($ride->time_fare, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Surge Charges</td>
                                <td style="text-align: right; font-weight: 600; color: #f59e0b;">+ ₹{{ number_format($ride->surge_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Promo Discount</td>
                                <td style="text-align: right; font-weight: 600; color: #10b981;">- ₹{{ number_format($ride->discount_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Taxes & GST</td>
                                <td style="text-align: right; font-weight: 600;">₹{{ number_format($ride->tax_amount, 2) }}</td>
                            </tr>
                            <tr style="background: #f8fafc; font-weight: 700;">
                                <td style="font-size: 1rem; color: #0f172a;">Total Fare Payable</td>
                                <td style="text-align: right; font-size: 1.15rem; color: var(--primary);">₹{{ number_format($ride->total_fare, 2) }}</td>
                            </tr>
                            <tr style="background: #ffffff; color: #64748b; font-size: 0.8rem;">
                                <td>Driver Earnings: ₹{{ number_format($ride->driver_earning, 2) }}</td>
                                <td style="text-align: right;">Platform Fee: ₹{{ number_format($ride->platform_fee, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Admin Status Overrides -->
                <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border-color);">
                    <h4 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-bottom: 12px;">Admin Action & Override</h4>
                    <form action="{{ route('admin.rides.status', $ride->id) }}" method="POST" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                        @csrf
                        <div style="flex: 1; min-width: 180px;">
                            <label class="form-label">Change Status</label>
                            <select name="status" class="form-control">
                                <option value="requested" {{ $ride->status === 'requested' ? 'selected' : '' }}>Requested</option>
                                <option value="driver_assigned" {{ $ride->status === 'driver_assigned' ? 'selected' : '' }}>Driver Assigned</option>
                                <option value="driver_arrived" {{ $ride->status === 'driver_arrived' ? 'selected' : '' }}>Driver Arrived</option>
                                <option value="in_progress" {{ $ride->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="completed" {{ $ride->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="cancelled" {{ $ride->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>
                        <div style="flex: 2; min-width: 200px;">
                            <label class="form-label">Cancellation Reason (if cancelling)</label>
                            <input type="text" name="cancel_reason" class="form-control" placeholder="e.g. Passenger no-show, driver breakdown..." value="{{ $ride->cancel_reason }}">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Trip</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Customer, Driver, Vehicle Details -->
        <div>
            <!-- Customer Card -->
            <div class="card">
                <h4 class="card-title" style="margin-bottom: 12px;">Customer Details</h4>
                @if($ride->customer)
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                        <div class="admin-avatar" style="width: 44px; height: 44px; font-size: 1rem;">
                            {{ substr($ride->customer->name, 0, 1) }}
                        </div>
                        <div>
                            <div style="font-weight: 700; color: #0f172a;">{{ $ride->customer->name }}</div>
                            <div style="font-size: 0.75rem; color: #64748b;">ID: {{ $ride->customer->customer_code }}</div>
                        </div>
                    </div>
                    <div style="font-size: 0.85rem; line-height: 1.6;">
                        <div><strong>Phone:</strong> {{ $ride->customer->phone }}</div>
                        <div><strong>Email:</strong> {{ $ride->customer->email }}</div>
                        <div><strong>Rating:</strong> ★ {{ $ride->customer->rating }}</div>
                    </div>
                    <div style="margin-top: 14px;">
                        <a href="{{ route('admin.customers.show', $ride->customer->id) }}" class="btn btn-secondary btn-sm" style="width: 100%;">View Customer Profile</a>
                    </div>
                @else
                    <p style="color: #94a3b8; font-size: 0.85rem;">Customer info unavailable.</p>
                @endif
            </div>

            <!-- Driver Card -->
            <div class="card">
                <h4 class="card-title" style="margin-bottom: 12px;">Assigned Driver</h4>
                @if($ride->driver)
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                        <div class="admin-avatar" style="width: 44px; height: 44px; font-size: 1rem; background: linear-gradient(135deg, #10b981, #0284c7);">
                            {{ substr($ride->driver->name, 0, 1) }}
                        </div>
                        <div>
                            <div style="font-weight: 700; color: #0f172a;">{{ $ride->driver->name }}</div>
                            <div style="font-size: 0.75rem; color: #64748b;">ID: {{ $ride->driver->driver_code }}</div>
                        </div>
                    </div>
                    <div style="font-size: 0.85rem; line-height: 1.6;">
                        <div><strong>Phone:</strong> {{ $ride->driver->phone }}</div>
                        <div><strong>License:</strong> {{ $ride->driver->license_number ?? 'N/A' }}</div>
                        <div><strong>Rating:</strong> ★ {{ $ride->driver->rating }}</div>
                    </div>
                    <div style="margin-top: 14px;">
                        <a href="{{ route('admin.drivers.show', $ride->driver->id) }}" class="btn btn-secondary btn-sm" style="width: 100%;">View Driver Profile</a>
                    </div>
                @else
                    <p style="color: #94a3b8; font-size: 0.85rem;">No driver assigned yet.</p>
                @endif
            </div>

            <!-- Vehicle Card -->
            <div class="card">
                <h4 class="card-title" style="margin-bottom: 12px;">Vehicle</h4>
                <div style="font-size: 0.85rem; line-height: 1.6;">
                    <div><strong>Vehicle Type:</strong> <span class="badge badge-neutral">{{ strtoupper($ride->vehicle_type) }}</span></div>
                    @if($ride->vehicle)
                        <div><strong>Plate Number:</strong> <span style="font-family: monospace; font-weight: 700;">{{ $ride->vehicle->reg_number }}</span></div>
                        <div><strong>Make & Model:</strong> {{ $ride->vehicle->make }} {{ $ride->vehicle->model }}</div>
                        <div><strong>Color:</strong> {{ $ride->vehicle->color ?? 'Standard' }}</div>
                    @else
                        <div style="color: #94a3b8; margin-top: 4px;">Standard {{ ucfirst($ride->vehicle_type) }} category</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection
