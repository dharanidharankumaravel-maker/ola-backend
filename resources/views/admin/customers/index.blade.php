@extends('admin.layouts.app')

@section('title', 'Alo Admin - Customer Management')
@section('page_title', 'Customer Management')

@section('content')

    <!-- Metrics Bar -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">TOTAL CUSTOMERS</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-top: 4px;">{{ number_format($totalCustomers) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #10b981; font-weight: 600;">ACTIVE USERS</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #10b981; margin-top: 4px;">{{ number_format($activeCustomers) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #ef4444; font-weight: 600;">BLOCKED</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #ef4444; margin-top: 4px;">{{ number_format($blockedCustomers) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #0284c7; font-weight: 600;">TOTAL RIDER SPENT</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #0284c7; margin-top: 4px;">₹{{ number_format($totalSpent, 2) }}</div>
        </div>
    </div>

    <!-- Filter & Action Header -->
    <div class="card" style="padding: 18px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <form method="GET" action="{{ route('admin.customers.index') }}" class="filter-bar" style="margin: 0; flex: 1;">
                <div class="filter-tabs">
                    <a href="{{ route('admin.customers.index', ['status' => 'all', 'search' => $search]) }}" class="tab-btn {{ $status === 'all' ? 'active' : '' }}">All</a>
                    <a href="{{ route('admin.customers.index', ['status' => 'active', 'search' => $search]) }}" class="tab-btn {{ $status === 'active' ? 'active' : '' }}">Active</a>
                    <a href="{{ route('admin.customers.index', ['status' => 'blocked', 'search' => $search]) }}" class="tab-btn {{ $status === 'blocked' ? 'active' : '' }}">Blocked</a>
                </div>

                <div class="search-box">
                    <svg class="search-icon-pos" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" class="search-input" placeholder="Search by customer name, phone, or ID..." value="{{ $search }}">
                </div>
            </form>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Name & Contact</th>
                        <th>Wallet Balance</th>
                        <th>Total Rides</th>
                        <th>Total Spent</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th>Joined Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr>
                            <td>
                                <strong style="color: var(--primary);">#{{ $customer->customer_code }}</strong>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="admin-avatar" style="width: 36px; height: 36px; font-size: 0.85rem; background: #fdf2f8; color: #db2777;">
                                        {{ substr($customer->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;">{{ $customer->name }}</div>
                                        <div style="font-size: 0.75rem; color: #94a3b8;">{{ $customer->phone }} &bull; {{ $customer->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong style="color: #0284c7;">₹{{ number_format($customer->wallet_balance, 2) }}</strong>
                            </td>
                            <td>
                                <div style="font-weight: 600;">{{ $customer->total_rides }}</div>
                            </td>
                            <td>
                                <strong>₹{{ number_format($customer->total_spent, 2) }}</strong>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #d97706;">★ {{ $customer->rating }}</span>
                            </td>
                            <td>
                                @if($customer->status === 'active')
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Blocked</span>
                                @endif
                            </td>
                            <td style="font-size: 0.78rem; color: #94a3b8;">
                                {{ $customer->created_at->format('M d, Y') }}
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <a href="{{ route('admin.customers.show', $customer->id) }}" class="btn btn-secondary btn-sm">View</a>
                                    <form action="{{ route('admin.customers.toggle-block', $customer->id) }}" method="POST" style="margin: 0;">
                                        @csrf
                                        <button type="submit" class="btn {{ $customer->status === 'blocked' ? 'btn-primary' : 'btn-danger' }} btn-sm">
                                            {{ $customer->status === 'blocked' ? 'Unblock' : 'Block' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 48px; color: #94a3b8;">
                                <div style="font-size: 1.1rem; font-weight: 600; color: #64748b;">No customers found</div>
                                <p style="font-size: 0.85rem; margin-top: 4px;">Users registering through the customer app will appear here.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($customers->hasPages())
            <div style="padding: 16px; border-top: 1px solid var(--border-color);">
                {{ $customers->links() }}
            </div>
        @endif
    </div>

@endsection
