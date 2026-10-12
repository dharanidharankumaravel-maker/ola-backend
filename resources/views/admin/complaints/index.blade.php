@extends('admin.layouts.app')

@section('title', 'Alo Admin - Complaints & Support Tickets')
@section('page_title', 'Complaints & Support Tickets')

@section('content')

    <!-- Metrics Bar -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">TOTAL TICKETS</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-top: 4px;">{{ number_format($totalTickets) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #ef4444; font-weight: 600;">OPEN TICKETS</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #ef4444; margin-top: 4px;">{{ number_format($openTickets) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #f59e0b; font-weight: 600;">INVESTIGATING</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b; margin-top: 4px;">{{ number_format($investigatingTickets) }}</div>
        </div>
        <div class="card" style="padding: 16px; margin: 0;">
            <div style="font-size: 0.75rem; color: #10b981; font-weight: 600;">RESOLVED</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #10b981; margin-top: 4px;">{{ number_format($resolvedTickets) }}</div>
        </div>
    </div>

    <!-- Filter Header -->
    <div class="card" style="padding: 18px; margin-bottom: 20px;">
        <div class="filter-tabs">
            <a href="{{ route('admin.complaints.index', ['status' => 'all']) }}" class="tab-btn {{ $status === 'all' ? 'active' : '' }}">All</a>
            <a href="{{ route('admin.complaints.index', ['status' => 'open']) }}" class="tab-btn {{ $status === 'open' ? 'active' : '' }}">Open</a>
            <a href="{{ route('admin.complaints.index', ['status' => 'investigating']) }}" class="tab-btn {{ $status === 'investigating' ? 'active' : '' }}">Investigating</a>
            <a href="{{ route('admin.complaints.index', ['status' => 'resolved']) }}" class="tab-btn {{ $status === 'resolved' ? 'active' : '' }}">Resolved</a>
            <a href="{{ route('admin.complaints.index', ['status' => 'rejected']) }}" class="tab-btn {{ $status === 'rejected' ? 'active' : '' }}">Rejected</a>
        </div>
    </div>

    <!-- Complaints Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Raised By</th>
                        <th>Category & Subject</th>
                        <th>Trip #</th>
                        <th>Priority</th>
                        <th>Created At</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($complaints as $c)
                        <tr>
                            <td>
                                <strong style="color: var(--primary);">#{{ $c->ticket_number }}</strong>
                            </td>
                            <td>
                                @if($c->customer)
                                    <div style="font-weight: 600;">{{ $c->customer->name }} (Rider)</div>
                                    <div style="font-size: 0.75rem; color: #94a3b8;">{{ $c->customer->phone }}</div>
                                @elseif($c->driver)
                                    <div style="font-weight: 600;">{{ $c->driver->name }} (Driver)</div>
                                    <div style="font-size: 0.75rem; color: #94a3b8;">{{ $c->driver->phone }}</div>
                                @else
                                    <span style="color: #94a3b8;">Anonymous</span>
                                @endif
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #0f172a;">{{ $c->subject }}</div>
                                <span class="badge badge-neutral" style="font-size: 0.7rem;">{{ ucwords(str_replace('_', ' ', $c->category)) }}</span>
                            </td>
                            <td>
                                @if($c->ride)
                                    <a href="{{ route('admin.rides.show', $c->ride->id) }}" style="color: #0284c7; font-weight: 600; text-decoration: none;">
                                        #{{ $c->ride->ride_number }}
                                    </a>
                                @else
                                    <span style="color: #94a3b8;">General</span>
                                @endif
                            </td>
                            <td>
                                @if($c->priority === 'critical')
                                    <span class="badge badge-danger">Critical</span>
                                @elseif($c->priority === 'high')
                                    <span class="badge badge-danger" style="background: #fef2f2; color: #b91c1c;">High</span>
                                @elseif($c->priority === 'medium')
                                    <span class="badge badge-warning">Medium</span>
                                @else
                                    <span class="badge badge-neutral">Low</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size: 0.85rem;">{{ $c->created_at->format('M d, Y') }}</span>
                            </td>
                            <td>
                                @if($c->status === 'open')
                                    <span class="badge badge-danger">Open</span>
                                @elseif($c->status === 'investigating')
                                    <span class="badge badge-warning">Investigating</span>
                                @elseif($c->status === 'resolved')
                                    <span class="badge badge-success">Resolved</span>
                                @else
                                    <span class="badge badge-neutral">Rejected</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.complaints.show', $c->id) }}" class="btn btn-secondary btn-sm">
                                    View & Reply
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 48px; color: #94a3b8;">
                                <div style="font-size: 1.1rem; font-weight: 600; color: #64748b;">No complaints found matching criteria</div>
                                <p style="font-size: 0.85rem; margin-top: 4px;">User support queries and trip dispute tickets will appear here.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($complaints->hasPages())
            <div style="padding: 16px; border-top: 1px solid var(--border-color);">
                {{ $complaints->links() }}
            </div>
        @endif
    </div>

@endsection
