<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CmsController extends Controller
{
    public function index()
    {
        $pages = CmsPage::latest()->get();
        return view('admin.cms.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.cms.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'required|in:published,draft',
        ]);

        $slug = Str::slug($request->title);
        $originalSlug = $slug;
        $count = 1;
        while (CmsPage::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-" . $count++;
        }

        $page = CmsPage::create([
            'title' => $request->title,
            'slug' => $slug,
            'content' => $request->content,
            'meta_title' => $request->meta_title ?? $request->title,
            'meta_description' => $request->meta_description,
            'status' => $request->status,
        ]);

        return redirect()->route('admin.cms.index')->with('success', "Page '{$page->title}' created successfully.");
    }

    public function edit($slug)
    {
        $page = CmsPage::where('slug', $slug)->firstOrFail();
        return view('admin.cms.edit', compact('page'));
    }

    public function update(Request $request, $slug)
    {
        $page = CmsPage::where('slug', $slug)->firstOrFail();
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'required|in:published,draft',
        ]);

        $page->update([
            'title' => $request->title,
            'content' => $request->content,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'status' => $request->status,
        ]);

        return back()->with('success', "CMS Page '{$page->title}' updated successfully.");
    }
}
