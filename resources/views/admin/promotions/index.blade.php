@extends('admin.layouts.app')

@section('title', 'Alo Admin - Promotions & Coupons')
@section('page_title', 'Promotions & Coupons')

@section('content')

    <!-- Metrics Bar -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">TOTAL PROMOS</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-top: 4px;">{{ number_format($totalPromos) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #10b981; font-weight: 600;">ACTIVE PROMOS</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #10b981; margin-top: 4px;">{{ number_format($activePromos) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #0284c7; font-weight: 600;">TOTAL REDEMPTIONS</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #0284c7; margin-top: 4px;">{{ number_format($totalRedemptions) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #f59e0b; font-weight: 600;">EST. RIDER SAVINGS</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b; margin-top: 4px;">₹{{ number_format($estSavings, 2) }}</div>
        </div>
    </div>

    <!-- Filter & Action Header -->
    <div class="card" style="padding: 18px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div class="filter-tabs">
                <a href="{{ route('admin.promotions.index', ['status' => 'all']) }}" class="tab-btn {{ $status === 'all' ? 'active' : '' }}">All</a>
                <a href="{{ route('admin.promotions.index', ['status' => 'active']) }}" class="tab-btn {{ $status === 'active' ? 'active' : '' }}">Active</a>
                <a href="{{ route('admin.promotions.index', ['status' => 'expired']) }}" class="tab-btn {{ $status === 'expired' ? 'active' : '' }}">Expired</a>
                <a href="{{ route('admin.promotions.index', ['status' => 'inactive']) }}" class="tab-btn {{ $status === 'inactive' ? 'active' : '' }}">Inactive</a>
            </div>

            <button type="button" class="btn btn-primary" onclick="document.getElementById('addPromoModal').classList.add('show')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Create New Promotion
            </button>
        </div>
    </div>

    <!-- Promotions Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Promo Code</th>
                        <th>Campaign Title</th>
                        <th>Discount</th>
                        <th>Min Ride</th>
                        <th>Redemptions / Limit</th>
                        <th>Expires At</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promotions as $promo)
                        <tr>
                            <td>
                                <span style="font-family: monospace; font-size: 0.95rem; font-weight: 700; background: #e0f2fe; color: #0284c7; padding: 4px 10px; border-radius: 6px; letter-spacing: 0.05em;">
                                    {{ $promo->code }}
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #0f172a;">{{ $promo->title }}</div>
                                @if($promo->description)
                                    <div style="font-size: 0.75rem; color: #64748b;">{{ $promo->description }}</div>
                                @endif
                            </td>
                            <td>
                                @if($promo->discount_type === 'percentage')
                                    <strong style="color: #10b981;">{{ (int)$promo->discount_value }}% OFF</strong>
                                    @if($promo->max_discount)
                                        <div style="font-size: 0.72rem; color: #94a3b8;">Up to ₹{{ $promo->max_discount }}</div>
                                    @endif
                                @else
                                    <strong style="color: #10b981;">₹{{ $promo->discount_value }} FLAT</strong>
                                @endif
                            </td>
                            <td>
                                @if($promo->min_ride_amount > 0)
                                    ₹{{ number_format($promo->min_ride_amount) }}
                                @else
                                    <span style="color: #94a3b8;">No Min</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $promo->total_used }}</strong>
                                <span style="color: #94a3b8;">/ {{ $promo->usage_limit ?? 'Unlimited' }}</span>
                            </td>
                            <td>
                                @if($promo->expires_at)
                                    <span style="font-size: 0.85rem;">{{ $promo->expires_at->format('M d, Y') }}</span>
                                @else
                                    <span style="color: #94a3b8;">Never</span>
                                @endif
                            </td>
                            <td>
                                @if($promo->status === 'active')
                                    <span class="badge badge-success">Active</span>
                                @elseif($promo->status === 'expired')
                                    <span class="badge badge-danger">Expired</span>
                                @else
                                    <span class="badge badge-neutral">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <form action="{{ route('admin.promotions.toggle', $promo->id) }}" method="POST" style="margin: 0;">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            {{ $promo->status === 'active' ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>

                                    <form action="{{ route('admin.promotions.destroy', $promo->id) }}" method="POST" onsubmit="return confirm('Delete this promotion?')" style="margin: 0;">
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
                                <div style="font-size: 1.1rem; font-weight: 600; color: #64748b;">No promotions found</div>
                                <p style="font-size: 0.85rem; margin-top: 4px;">Click "Create New Promotion" to launch discounts and coupon codes.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($promotions->hasPages())
            <div style="padding: 16px; border-top: 1px solid var(--border-color);">
                {{ $promotions->links() }}
            </div>
        @endif
    </div>

    <!-- Create Promotion Modal -->
    <div class="modal-overlay" id="addPromoModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="card-title">Create New Promotion</h3>
                <button type="button" class="close-modal-btn" onclick="document.getElementById('addPromoModal').classList.remove('show')">&times;</button>
            </div>
            <form action="{{ route('admin.promotions.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">Promo Code * (e.g. SAVE20, SUMMER50)</label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. SAVE20" style="text-transform: uppercase;" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Campaign Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Summer Weekend Discount" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Discount Type *</label>
                        <select name="discount_type" class="form-control" required>
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Amount (₹)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Discount Value * (e.g. 20% or 150)</label>
                        <input type="number" step="0.01" name="discount_value" class="form-control" placeholder="e.g. 20" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Max Discount Cap (₹) (Optional)</label>
                        <input type="number" step="0.01" name="max_discount" class="form-control" placeholder="e.g. 100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min Ride Amount (₹)</label>
                        <input type="number" step="0.01" name="min_ride_amount" class="form-control" placeholder="e.g. 150">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Usage Limit (Rides)</label>
                        <input type="number" name="usage_limit" class="form-control" placeholder="e.g. 1000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expires_at" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Initial Status</label>
                    <select name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('addPromoModal').classList.remove('show')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Promotion</button>
                </div>
            </form>
        </div>
    </div>

@endsection
