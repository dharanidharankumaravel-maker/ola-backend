@extends('admin.layouts.app')

@section('title', 'Alo Admin - Create CMS Page')
@section('page_title', 'Create Content Page')

@section('content')

    <div style="margin-bottom: 20px;">
        <a href="{{ route('admin.cms.index') }}" class="btn btn-secondary btn-sm">&larr; Back to CMS</a>
    </div>

    <div class="card" style="max-width: 900px;">
        <h3 class="card-title" style="margin-bottom: 14px;">Page Configuration</h3>

        <form action="{{ route('admin.cms.store') }}" method="POST">
            @csrf

            <div class="form-group">
                <label class="form-label">Page Title *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Refund & Cancellation Policy" required value="{{ old('title') }}">
            </div>

            <div class="form-group">
                <label class="form-label">HTML Content *</label>
                <textarea name="content" class="form-control" rows="12" placeholder="<h2>Page Heading</h2><p>Page body content...</p>" required>{{ old('content') }}</textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Meta SEO Title</label>
                    <input type="text" name="meta_title" class="form-control" placeholder="Meta title..." value="{{ old('meta_title') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Publishing Status</label>
                    <select name="status" class="form-control">
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Meta SEO Description</label>
                <textarea name="meta_description" class="form-control" rows="3" placeholder="Brief summary for search engines...">{{ old('meta_description') }}</textarea>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary">Save & Publish Page</button>
                <a href="{{ route('admin.cms.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

@endsection
