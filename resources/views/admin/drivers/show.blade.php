@extends('admin.layouts.app')

@section('title', "Alo Admin - Driver Profile: {$driver->name}")
@section('page_title', "Driver Profile: {$driver->name}")

@section('content')

    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <a href="{{ route('admin.drivers.index') }}" class="btn btn-secondary btn-sm">&larr; Back to Drivers</a>
        <div style="display: flex; gap: 8px;">
            @if($driver->status === 'pending')
                <form action="{{ route('admin.drivers.approve', $driver->id) }}" method="POST" style="margin: 0;">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">Approve Driver</button>
                </form>
                <form action="{{ route('admin.drivers.reject', $driver->id) }}" method="POST" style="margin: 0;">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm" style="color: #ef4444;">Reject</button>
                </form>
            @endif

            <form action="{{ route('admin.drivers.toggle-block', $driver->id) }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="btn {{ $driver->status === 'blocked' ? 'btn-primary' : 'btn-danger' }} btn-sm">
                    {{ $driver->status === 'blocked' ? 'Unblock Driver' : 'Block Driver' }}
                </button>
            </form>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
        <!-- Left: Profile, Status, Vehicle -->
        <div>
            <div class="card" style="text-align: center;">
                <div class="admin-avatar" style="width: 72px; height: 72px; font-size: 1.8rem; margin: 0 auto 14px; background: linear-gradient(135deg, #0284c7, #6366f1);">
                    {{ substr($driver->name, 0, 1) }}
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #0f172a;">{{ $driver->name }}</h3>
                <div style="font-size: 0.8rem; color: #64748b; font-family: monospace; margin-top: 2px;">{{ $driver->driver_code }}</div>

                <div style="margin-top: 12px;">
                    @if($driver->status === 'approved')
                        <span class="badge badge-success">Approved</span>
                    @elseif($driver->status === 'pending')
                        <span class="badge badge-warning">Pending Verification</span>
                    @elseif($driver->status === 'blocked')
                        <span class="badge badge-danger">Blocked</span>
                    @else
                        <span class="badge badge-neutral">{{ $driver->status }}</span>
                    @endif

                    @if($driver->online_status === 'online')
                        <span class="badge badge-success" style="margin-left: 6px;">Online</span>
                    @else
                        <span class="badge badge-neutral" style="margin-left: 6px;">Offline</span>
                    @endif
                </div>

                <div style="border-top: 1px solid #e2e8f0; margin-top: 20px; padding-top: 18px; text-align: left; font-size: 0.88rem; line-height: 1.8;">
                    <div><strong>Phone:</strong> {{ $driver->phone }}</div>
                    <div><strong>Email:</strong> {{ $driver->email }}</div>
                    <div><strong>Driving License:</strong> {{ $driver->license_number ?? 'Not uploaded' }}</div>
                    <div><strong>Vehicle RC:</strong> {{ $driver->rc_number ?? 'Not uploaded' }}</div>
                    <div><strong>Rating:</strong> ★ {{ $driver->rating }} / 5.0</div>
                    <div><strong>Joined Date:</strong> {{ $driver->created_at->format('M d, Y') }}</div>
                </div>
            </div>

            <!-- Assigned Vehicle Card -->
            <div class="card">
                <h4 class="card-title" style="margin-bottom: 12px;">Assigned Vehicle</h4>
                @if($driver->vehicle)
                    <div style="font-size: 0.88rem; line-height: 1.8;">
                        <div><strong>Plate Number:</strong> <span style="font-family: monospace; font-weight: 700;">{{ $driver->vehicle->reg_number }}</span></div>
                        <div><strong>Type:</strong> <span class="badge badge-neutral">{{ strtoupper($driver->vehicle->vehicle_type) }}</span></div>
                        <div><strong>Make & Model:</strong> {{ $driver->vehicle->make }} {{ $driver->vehicle->model }}</div>
                        <div><strong>Owner:</strong> {{ $driver->vehicle->owner_name }}</div>
                        <div><strong>Status:</strong> <span class="badge badge-success">{{ $driver->vehicle->status }}</span></div>
                    </div>
                @else
                    <p style="color: #94a3b8; font-size: 0.85rem;">No vehicle assigned to this driver yet.</p>
                @endif
            </div>

            <!-- Payout & Bank Info -->
            <div class="card">
                <h4 class="card-title" style="margin-bottom: 12px;">Payout & Banking</h4>
                <div style="font-size: 0.88rem; line-height: 1.8;">
                    <div><strong>UPI ID:</strong> {{ $driver->upi_id ?? 'Not provided' }}</div>
                    <div><strong>Bank:</strong> {{ $driver->bank_name ?? 'N/A' }}</div>
                    <div><strong>Account No:</strong> {{ $driver->account_number ?? 'N/A' }}</div>
                    <div><strong>IFSC Code:</strong> {{ $driver->ifsc_code ?? 'N/A' }}</div>
                </div>
            </div>
        </div>

        <!-- Right: Earnings, Trip History, Verification Status -->
        <div>
            <!-- Financial Metric Tiles -->
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div class="card" style="padding: 18px; margin: 0;">
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">TOTAL TRIPS</div>
                    <div style="font-size: 1.6rem; font-weight: 700; color: #0f172a; margin-top: 4px;">{{ $driver->total_trips }}</div>
                </div>
                <div class="card" style="padding: 18px; margin: 0;">
                    <div style="font-size: 0.75rem; color: #10b981; font-weight: 600;">TOTAL EARNINGS</div>
                    <div style="font-size: 1.6rem; font-weight: 700; color: #10b981; margin-top: 4px;">₹{{ number_format($driver->total_earnings, 2) }}</div>
                </div>
                <div class="card" style="padding: 18px; margin: 0;">
                    <div style="font-size: 0.75rem; color: #0284c7; font-weight: 600;">WALLET BALANCE</div>
                    <div style="font-size: 1.6rem; font-weight: 700; color: #0284c7; margin-top: 4px;">₹{{ number_format($driver->wallet_balance, 2) }}</div>
                </div>
            </div>

            <!-- Edit Details Form -->
            <div class="card">
                <h4 class="card-title" style="margin-bottom: 14px;">Edit Driver Partner Information</h4>
                <form action="{{ route('admin.drivers.update', $driver->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" value="{{ $driver->name }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="{{ $driver->phone }}" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="{{ $driver->email }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Driving License</label>
                            <input type="text" name="license_number" class="form-control" value="{{ $driver->license_number }}">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Account Status</label>
                            <select name="status" class="form-control">
                                <option value="pending" {{ $driver->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ $driver->status === 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ $driver->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                <option value="blocked" {{ $driver->status === 'blocked' ? 'selected' : '' }}>Blocked</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Duty Status</label>
                            <select name="online_status" class="form-control">
                                <option value="online" {{ $driver->online_status === 'online' ? 'selected' : '' }}>Online</option>
                                <option value="offline" {{ $driver->online_status === 'offline' ? 'selected' : '' }}>Offline</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Assigned Vehicle</label>
                            <select name="vehicle_id" class="form-control">
                                <option value="">-- None --</option>
                                @foreach($vehicles as $veh)
                                    <option value="{{ $veh->id }}" {{ $driver->vehicle_id == $veh->id ? 'selected' : '' }}>{{ $veh->reg_number }} ({{ $veh->model }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 14px;">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>

            <!-- Recent Rides Table -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 18px 20px; border-bottom: 1px solid var(--border-color);">
                    <h4 class="card-title">Recent Trips by Driver</h4>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ride ID</th>
                                <th>Pickup / Dropoff</th>
                                <th>Fare</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($driver->rides as $r)
                                <tr>
                                    <td><strong style="color: var(--primary);">#{{ $r->ride_number }}</strong></td>
                                    <td>
                                        <div style="font-size: 0.8rem; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $r->pickup_address }} &rarr; {{ $r->dropoff_address }}</div>
                                    </td>
                                    <td><strong>₹{{ number_format($r->total_fare, 2) }}</strong></td>
                                    <td><span class="badge badge-success">{{ $r->status }}</span></td>
                                    <td style="font-size: 0.75rem; color: #94a3b8;">{{ $r->created_at->format('M d, Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 24px; color: #94a3b8;">No rides completed by this driver yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

@endsection
