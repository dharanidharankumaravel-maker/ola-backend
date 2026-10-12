@extends('admin.layouts.app')

@section('title', 'Alo Admin - Content Management (CMS)')
@section('page_title', 'Content Management (CMS)')

@section('content')

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <p class="card-subtitle">Manage legal terms, policy documents, and app content pages.</p>
        <a href="{{ route('admin.cms.create') }}" class="btn btn-primary">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Create New Page
        </a>
    </div>

    <!-- CMS Pages Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Page Title</th>
                        <th>Slug / Route</th>
                        <th>Status</th>
                        <th>Last Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pages as $page)
                        <tr>
                            <td>
                                <strong style="font-size: 0.95rem; color: #0f172a;">{{ $page->title }}</strong>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-size: 0.85rem; color: #0284c7; background: #e0f2fe; padding: 3px 8px; border-radius: 6px;">
                                    /{{ $page->slug }}
                                </span>
                            </td>
                            <td>
                                @if($page->status === 'published')
                                    <span class="badge badge-success">Published</span>
                                @else
                                    <span class="badge badge-neutral">Draft</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size: 0.85rem;">{{ $page->updated_at->format('M d, Y h:i A') }}</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.cms.edit', $page->slug) }}" class="btn btn-secondary btn-sm">
                                    Edit Page
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 48px; color: #94a3b8;">
                                No content pages found. Click "Create New Page" to add one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
