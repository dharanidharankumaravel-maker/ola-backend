<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::latest()->paginate(20);
        return view('admin.notifications.index', compact('notifications'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:150',
            'message' => 'required|string',
            'target_type' => 'required|in:all,customers,drivers',
        ]);

        Notification::create([
            'title' => $request->title,
            'message' => $request->message,
            'target_type' => $request->target_type,
        ]);

        return back()->with('success', "Notification broadcast sent successfully to {$request->target_type}.");
    }
}
