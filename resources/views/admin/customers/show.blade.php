@extends('admin.layouts.app')

@section('title', "Alo Admin - Customer Profile: {$customer->name}")
@section('page_title', "Customer Profile: {$customer->name}")

@section('content')

    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <a href="{{ route('admin.customers.index') }}" class="btn btn-secondary btn-sm">&larr; Back to Customers</a>
        <form action="{{ route('admin.customers.toggle-block', $customer->id) }}" method="POST" style="margin: 0;">
            @csrf
            <button type="submit" class="btn {{ $customer->status === 'blocked' ? 'btn-primary' : 'btn-danger' }} btn-sm">
                {{ $customer->status === 'blocked' ? 'Unblock Customer' : 'Block Customer' }}
            </button>
        </form>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
        <!-- Left: Customer Profile & Wallet Controls -->
        <div>
            <div class="card" style="text-align: center;">
                <div class="admin-avatar" style="width: 72px; height: 72px; font-size: 1.8rem; margin: 0 auto 14px; background: linear-gradient(135deg, #db2777, #f43f5e);">
                    {{ substr($customer->name, 0, 1) }}
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #0f172a;">{{ $customer->name }}</h3>
                <div style="font-size: 0.8rem; color: #64748b; font-family: monospace; margin-top: 2px;">{{ $customer->customer_code }}</div>

                <div style="margin-top: 10px;">
                    @if($customer->status === 'active')
                        <span class="badge badge-success">Active Rider</span>
                    @else
                        <span class="badge badge-danger">Blocked</span>
                    @endif
                </div>

                <div style="border-top: 1px solid #e2e8f0; margin-top: 20px; padding-top: 18px; text-align: left; font-size: 0.88rem; line-height: 1.8;">
                    <div><strong>Phone:</strong> {{ $customer->phone }}</div>
                    <div><strong>Email:</strong> {{ $customer->email }}</div>
                    <div><strong>Rating:</strong> ★ {{ $customer->rating }} / 5.0</div>
                    <div><strong>Registered On:</strong> {{ $customer->created_at->format('M d, Y') }}</div>
                </div>
            </div>

            <!-- Wallet Management Card -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Alo Wallet</h4>
                    <span class="badge badge-info">Balance</span>
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: #0284c7; font-family: 'Outfit', sans-serif;">
                    ₹{{ number_format($customer->wallet_balance, 2) }}
                </div>

                <div style="margin-top: 16px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                    <h5 style="font-size: 0.85rem; font-weight: 600; margin-bottom: 10px;">Admin Wallet Adjustment</h5>
                    <form action="{{ route('admin.customers.adjust-wallet', $customer->id) }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label class="form-label">Action</label>
                            <select name="type" class="form-control">
                                <option value="credit">Credit (+) Add to Wallet</option>
                                <option value="debit">Debit (-) Deduct from Wallet</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount (₹)</label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="e.g. 200" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">Apply Adjustment</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Ride History & Transactions -->
        <div>
            <!-- Stats Tiles -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div class="card" style="padding: 18px; margin: 0;">
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">TOTAL RIDES COMPLETED</div>
                    <div style="font-size: 1.6rem; font-weight: 700; color: #0f172a; margin-top: 4px;">{{ $customer->total_rides }}</div>
                </div>
                <div class="card" style="padding: 18px; margin: 0;">
                    <div style="font-size: 0.75rem; color: #10b981; font-weight: 600;">TOTAL SPENT</div>
                    <div style="font-size: 1.6rem; font-weight: 700; color: #10b981; margin-top: 4px;">₹{{ number_format($customer->total_spent, 2) }}</div>
                </div>
            </div>

            <!-- Customer Trip History -->
            <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
                <div style="padding: 18px 20px; border-bottom: 1px solid var(--border-color);">
                    <h4 class="card-title">Recent Trips</h4>
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
                            @forelse($customer->rides as $r)
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
                                    <td colspan="5" style="text-align: center; padding: 24px; color: #94a3b8;">This customer has not taken any rides yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Customer Payments -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 18px 20px; border-bottom: 1px solid var(--border-color);">
                    <h4 class="card-title">Payment & Wallet Transactions</h4>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($customer->payments as $p)
                                <tr>
                                    <td><strong style="color: #64748b;">{{ $p->payment_number }}</strong></td>
                                    <td><strong style="color: #0284c7;">₹{{ number_format($p->amount, 2) }}</strong></td>
                                    <td><span class="badge badge-neutral">{{ strtoupper($p->payment_method) }}</span></td>
                                    <td><span class="badge badge-success">{{ ucfirst($p->status) }}</span></td>
                                    <td style="font-size: 0.75rem; color: #94a3b8;">{{ $p->created_at->format('M d, h:i A') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 24px; color: #94a3b8;">No transactions recorded for this customer.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

@endsection
