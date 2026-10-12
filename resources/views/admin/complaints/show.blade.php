@extends('admin.layouts.app')

@section('title', "Alo Admin - Support Ticket #{$complaint->ticket_number}")
@section('page_title', "Ticket #{$complaint->ticket_number}")

@section('content')

    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <a href="{{ route('admin.complaints.index') }}" class="btn btn-secondary btn-sm">&larr; Back to Complaints</a>
        <div>
            @if($complaint->status === 'open')
                <span class="badge badge-danger" style="font-size: 0.9rem; padding: 6px 12px;">Open</span>
            @elseif($complaint->status === 'investigating')
                <span class="badge badge-warning" style="font-size: 0.9rem; padding: 6px 12px;">Under Investigation</span>
            @elseif($complaint->status === 'resolved')
                <span class="badge badge-success" style="font-size: 0.9rem; padding: 6px 12px;">Resolved</span>
            @else
                <span class="badge badge-neutral" style="font-size: 0.9rem; padding: 6px 12px;">Rejected</span>
            @endif
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <!-- Left: Ticket Conversation & Replies -->
        <div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ $complaint->subject }}</h3>
                        <p class="card-subtitle">Category: {{ ucwords(str_replace('_', ' ', $complaint->category)) }} &bull; Priority: {{ ucfirst($complaint->priority) }}</p>
                    </div>
                </div>

                <!-- Original Issue Description -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-bottom: 24px;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Initial Issue Description:</div>
                    <p style="font-size: 0.92rem; color: #1e293b; line-height: 1.6;">{{ $complaint->description }}</p>
                    <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 10px;">Submitted on {{ $complaint->created_at->format('M d, Y h:i A') }}</div>
                </div>

                <!-- Thread History -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-bottom: 14px;">Conversation & Replies</h4>

                <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 24px;">
                    @forelse($complaint->replies as $reply)
                        <div style="padding: 14px; border-radius: 10px; border: 1px solid {{ $reply->sender_type === 'admin' ? '#bfdbfe' : '#e2e8f0' }}; background: {{ $reply->sender_type === 'admin' ? '#eff6ff' : '#ffffff' }};">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <strong style="font-size: 0.85rem; color: {{ $reply->sender_type === 'admin' ? '#1e40af' : '#0f172a' }};">
                                    {{ $reply->sender_name ?? ucfirst($reply->sender_type) }}
                                    @if($reply->sender_type === 'admin')
                                        <span class="badge badge-info" style="font-size: 0.65rem; margin-left: 6px;">Support Executive</span>
                                    @endif
                                </strong>
                                <span style="font-size: 0.72rem; color: #94a3b8;">{{ $reply->created_at->format('M d, h:i A') }}</span>
                            </div>
                            <p style="font-size: 0.88rem; color: #334155; line-height: 1.5;">{{ $reply->message }}</p>
                        </div>
                    @empty
                        <div style="padding: 18px; text-align: center; color: #94a3b8; font-size: 0.85rem; background: #f8fafc; border-radius: 8px;">
                            No responses sent yet.
                        </div>
                    @endforelse
                </div>

                <!-- Reply Composer -->
                <form action="{{ route('admin.complaints.reply', $complaint->id) }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Post Official Response to Customer / Driver</label>
                        <textarea name="message" class="form-control" rows="4" placeholder="Type your response here..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Response</button>
                </form>
            </div>
        </div>

        <!-- Right: Ticket Status & Associated Entities -->
        <div>
            <!-- Status Update Card -->
            <div class="card">
                <h4 class="card-title" style="margin-bottom: 14px;">Update Ticket Status</h4>
                <form action="{{ route('admin.complaints.status', $complaint->id) }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Ticket Status</label>
                        <select name="status" class="form-control">
                            <option value="open" {{ $complaint->status === 'open' ? 'selected' : '' }}>Open</option>
                            <option value="investigating" {{ $complaint->status === 'investigating' ? 'selected' : '' }}>Under Investigation</option>
                            <option value="resolved" {{ $complaint->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                            <option value="rejected" {{ $complaint->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">Update Status</button>
                </form>
            </div>

            <!-- Associated Entities -->
            <div class="card">
                <h4 class="card-title" style="margin-bottom: 12px;">Associated Details</h4>
                <div style="font-size: 0.88rem; line-height: 1.8;">
                    @if($complaint->customer)
                        <div><strong>Customer:</strong> <a href="{{ route('admin.customers.show', $complaint->customer->id) }}" style="color: #0284c7; text-decoration: none;">{{ $complaint->customer->name }}</a></div>
                        <div><strong>Phone:</strong> {{ $complaint->customer->phone }}</div>
                    @endif

                    @if($complaint->driver)
                        <div><strong>Driver:</strong> <a href="{{ route('admin.drivers.show', $complaint->driver->id) }}" style="color: #0284c7; text-decoration: none;">{{ $complaint->driver->name }}</a></div>
                        <div><strong>Phone:</strong> {{ $complaint->driver->phone }}</div>
                    @endif

                    @if($complaint->ride)
                        <div><strong>Trip #:</strong> <a href="{{ route('admin.rides.show', $complaint->ride->id) }}" style="color: #0284c7; text-decoration: none;">#{{ $complaint->ride->ride_number }}</a></div>
                        <div><strong>Trip Fare:</strong> ₹{{ number_format($complaint->ride->total_fare, 2) }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection
