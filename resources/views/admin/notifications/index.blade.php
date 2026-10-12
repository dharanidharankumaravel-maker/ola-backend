@extends('admin.layouts.app')

@section('title', 'Alo Admin - Broadcast Notifications')
@section('page_title', 'Broadcast Notifications')

@section('content')

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
        <!-- Left: Send Notification Composer -->
        <div>
            <div class="card">
                <h3 class="card-title" style="margin-bottom: 6px;">Send Announcement</h3>
                <p class="card-subtitle" style="margin-bottom: 18px;">Send push notifications directly to driver or customer mobile apps.</p>

                <form action="{{ route('admin.notifications.send') }}" method="POST">
                    @csrf

                    <div class="form-group">
                        <label class="form-label">Target Audience *</label>
                        <select name="target_type" class="form-control" required>
                            <option value="all">Broadcast to All (Everyone)</option>
                            <option value="customers">Customers App Users Only</option>
                            <option value="drivers">Driver Partners App Only</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Notification Title *</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Surge Pricing Alert or Weekend Offer!" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Message Content *</label>
                        <textarea name="message" class="form-control" rows="5" placeholder="Enter message text..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        Send Push Broadcast
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Broadcast Logs & History -->
        <div>
            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 18px 20px; border-bottom: 1px solid var(--border-color);">
                    <h4 class="card-title">Broadcast History & Sent Messages</h4>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Audience</th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Sent Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($notifications as $n)
                                <tr>
                                    <td>
                                        <span class="badge {{ $n->target_type === 'drivers' ? 'badge-warning' : ($n->target_type === 'customers' ? 'badge-info' : 'badge-neutral') }}">
                                            {{ strtoupper($n->target_type) }}
                                        </span>
                                    </td>
                                    <td><strong style="color: #0f172a;">{{ $n->title }}</strong></td>
                                    <td style="font-size: 0.85rem; color: #475569; max-width: 280px;">{{ $n->message }}</td>
                                    <td style="font-size: 0.8rem; color: #94a3b8;">{{ $n->created_at->format('M d, Y h:i A') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 48px; color: #94a3b8;">
                                        No broadcast notifications sent yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($notifications->hasPages())
                    <div style="padding: 16px; border-top: 1px solid var(--border-color);">
                        {{ $notifications->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

@endsection
