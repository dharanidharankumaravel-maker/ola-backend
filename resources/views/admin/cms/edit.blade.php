@extends('admin.layouts.app')

@section('title', "Alo Admin - Edit CMS: {$page->title}")
@section('page_title', "Edit: {$page->title}")

@section('content')

    <div style="margin-bottom: 20px;">
        <a href="{{ route('admin.cms.index') }}" class="btn btn-secondary btn-sm">&larr; Back to CMS</a>
    </div>

    <div class="card" style="max-width: 900px;">
        <div class="card-header">
            <div>
                <h3 class="card-title">Edit Content Page</h3>
                <p class="card-subtitle">Slug: <span style="font-family: monospace; color: #0284c7;">/{{ $page->slug }}</span></p>
            </div>
            <div>
                @if($page->status === 'published')
                    <span class="badge badge-success">Published</span>
                @else
                    <span class="badge badge-neutral">Draft</span>
                @endif
            </div>
        </div>

        <form action="{{ route('admin.cms.update', $page->slug) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label">Page Title *</label>
                <input type="text" name="title" class="form-control" value="{{ $page->title }}" required>
            </div>

            <div class="form-group">
                <label class="form-label">HTML Content *</label>
                <textarea name="content" class="form-control" rows="14" required>{{ $page->content }}</textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Meta SEO Title</label>
                    <input type="text" name="meta_title" class="form-control" value="{{ $page->meta_title }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Publishing Status</label>
                    <select name="status" class="form-control">
                        <option value="published" {{ $page->status === 'published' ? 'selected' : '' }}>Published</option>
                        <option value="draft" {{ $page->status === 'draft' ? 'selected' : '' }}>Draft</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Meta SEO Description</label>
                <textarea name="meta_description" class="form-control" rows="3">{{ $page->meta_description }}</textarea>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary">Update Page</button>
                <a href="{{ route('admin.cms.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

@endsection
