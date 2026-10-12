<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::all()->pluck('value', 'key')->toArray();
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->except(['_token', '_method']);

        foreach ($data as $key => $value) {
            $group = str_starts_with($key, 'fare_') || in_array($key, ['commission_percentage', 'gst_percentage']) 
                ? 'pricing' 
                : 'general';
            SystemSetting::set($key, $value, $group);
        }

        return back()->with('success', 'System settings saved successfully.');
    }
}
