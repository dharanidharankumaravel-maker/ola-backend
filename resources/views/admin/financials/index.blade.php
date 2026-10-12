@extends('admin.layouts.app')

@section('title', 'Alo Admin - Financials Dashboard')
@section('page_title', 'Financials Dashboard')

@section('content')

    <!-- Metrics Bar -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">TOTAL PLATFORM REVENUE</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-top: 4px;">₹{{ number_format($totalRevenue, 2) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #10b981; font-weight: 600;">NET COMMISSION EARNED</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #10b981; margin-top: 4px;">₹{{ number_format($platformCommission, 2) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #f59e0b; font-weight: 600;">PENDING DRIVER PAYOUTS</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b; margin-top: 4px;">₹{{ number_format($pendingPayoutsSum, 2) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #0284c7; font-weight: 600;">COMPLETED TRIPS</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #0284c7; margin-top: 4px;">{{ number_format($totalCompletedTrips) }}</div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="card" style="padding: 14px 18px; margin-bottom: 20px;">
        <div class="filter-tabs">
            <a href="{{ route('admin.financials.index', ['tab' => 'payouts']) }}" class="tab-btn {{ $tab === 'payouts' ? 'active' : '' }}">Driver Payouts</a>
            <a href="{{ route('admin.financials.index', ['tab' => 'transactions']) }}" class="tab-btn {{ $tab === 'transactions' ? 'active' : '' }}">Customer Payments</a>
        </div>
    </div>

    @if($tab === 'payouts')
        <!-- Driver Payouts Table -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 18px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h4 class="card-title">Driver Payout Requests & Settlement</h4>
                <span class="badge badge-warning">Settlements</span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Payout ID</th>
                            <th>Driver</th>
                            <th>Amount</th>
                            <th>Payout Method / Details</th>
                            <th>Requested On</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payouts as $payout)
                            <tr>
                                <td>
                                    <strong style="color: var(--primary);">#{{ $payout->payout_number }}</strong>
                                </td>
                                <td>
                                    @if($payout->driver)
                                        <div style="font-weight: 600;">{{ $payout->driver->name }}</div>
                                        <div style="font-size: 0.75rem; color: #64748b;">{{ $payout->driver->phone }} &bull; {{ $payout->driver->driver_code }}</div>
                                    @else
                                        <span style="color: #94a3b8;">Unknown Driver</span>
                                    @endif
                                </td>
                                <td>
                                    <strong style="font-size: 1rem; color: #0f172a;">₹{{ number_format($payout->amount, 2) }}</strong>
                                </td>
                                <td>
                                    @if($payout->payout_method === 'upi')
                                        <div><span class="badge badge-info">UPI</span> {{ $payout->upi_id ?? $payout->driver->upi_id ?? 'N/A' }}</div>
                                    @else
                                        <div><span class="badge badge-neutral">BANK</span> {{ $payout->bank_name ?? 'A/C: ' . $payout->account_number }}</div>
                                        <div style="font-size: 0.75rem; color: #94a3b8;">IFSC: {{ $payout->ifsc_code }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span style="font-size: 0.85rem;">{{ $payout->created_at->format('M d, Y h:i A') }}</span>
                                </td>
                                <td>
                                    @if($payout->status === 'paid')
                                        <span class="badge badge-success">Paid</span>
                                        @if($payout->transaction_reference)
                                            <div style="font-size: 0.72rem; color: #64748b; font-family: monospace;">Ref: {{ $payout->transaction_reference }}</div>
                                        @endif
                                    @elseif($payout->status === 'pending')
                                        <span class="badge badge-warning">Pending</span>
                                    @else
                                        <span class="badge badge-danger">Rejected</span>
                                    @endif
                                </td>
                                <td>
                                    @if($payout->status === 'pending')
                                        <form action="{{ route('admin.financials.payouts.process', $payout->id) }}" method="POST" style="display: flex; gap: 6px; align-items: center; margin: 0;">
                                            @csrf
                                            <input type="text" name="transaction_reference" placeholder="Bank/UPI Ref #" style="padding: 4px 8px; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.78rem; width: 120px;" required>
                                            <input type="hidden" name="status" value="paid">
                                            <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                                        </form>
                                    @else
                                        <span style="font-size: 0.8rem; color: #94a3b8;">Settled</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 48px; color: #94a3b8;">
                                    <div style="font-size: 1.1rem; font-weight: 600; color: #64748b;">No driver payouts found</div>
                                    <p style="font-size: 0.85rem; margin-top: 4px;">When drivers request earnings payouts from the driver app, requests will appear here.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($payouts->hasPages())
                <div style="padding: 16px; border-top: 1px solid var(--border-color);">
                    {{ $payouts->links() }}
                </div>
            @endif
        </div>
    @else
        <!-- Customer Payments Table -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 18px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h4 class="card-title">Customer Ride Payments & Gateways</h4>
                <span class="badge badge-info">Transactions</span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Trip #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Transaction Ref</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $pmt)
                            <tr>
                                <td>
                                    <strong style="color: var(--primary);">#{{ $pmt->payment_number }}</strong>
                                </td>
                                <td>
                                    @if($pmt->ride)
                                        <a href="{{ route('admin.rides.show', $pmt->ride->id) }}" style="font-weight: 600; color: #0284c7; text-decoration: none;">
                                            #{{ $pmt->ride->ride_number }}
                                        </a>
                                    @else
                                        <span style="color: #94a3b8;">Direct Wallet</span>
                                    @endif
                                </td>
                                <td>
                                    @if($pmt->customer)
                                        <div style="font-weight: 600;">{{ $pmt->customer->name }}</div>
                                        <div style="font-size: 0.75rem; color: #64748b;">{{ $pmt->customer->phone }}</div>
                                    @else
                                        <span style="color: #94a3b8;">Unknown</span>
                                    @endif
                                </td>
                                <td>
                                    <strong style="font-size: 0.95rem; color: #0f172a;">₹{{ number_format($pmt->amount, 2) }}</strong>
                                </td>
                                <td>
                                    <span class="badge badge-neutral">{{ strtoupper($pmt->payment_method) }}</span>
                                </td>
                                <td>
                                    <span style="font-family: monospace; font-size: 0.8rem; color: #64748b;">{{ $pmt->transaction_id ?? 'N/A' }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-success">{{ ucfirst($pmt->status) }}</span>
                                </td>
                                <td>
                                    <span style="font-size: 0.85rem;">{{ $pmt->created_at->format('M d, Y h:i A') }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 48px; color: #94a3b8;">
                                    <div style="font-size: 1.1rem; font-weight: 600; color: #64748b;">No payment records found</div>
                                    <p style="font-size: 0.85rem; margin-top: 4px;">Completed trip payments and wallet top-ups will be logged here.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($payments->hasPages())
                <div style="padding: 16px; border-top: 1px solid var(--border-color);">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>
    @endif

@endsection
