<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $query = Promotion::latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $promotions = $query->paginate(15)->withQueryString();

        $totalPromos = Promotion::count();
        $activePromos = Promotion::where('status', 'active')->count();
        $totalRedemptions = Promotion::sum('total_used');
        $estSavings = Promotion::sum('discount_value') * ($totalRedemptions > 0 ? $totalRedemptions : 1);

        return view('admin.promotions.index', compact(
            'promotions',
            'status',
            'totalPromos',
            'activePromos',
            'totalRedemptions',
            'estSavings'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:promotions,code|max:20',
            'title' => 'required|string|max:100',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:1',
            'max_discount' => 'nullable|numeric|min:0',
            'min_ride_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'status' => 'required|in:active,expired,inactive',
        ]);

        $promo = Promotion::create([
            'code' => strtoupper($request->code),
            'title' => $request->title,
            'description' => $request->description,
            'discount_type' => $request->discount_type,
            'discount_value' => $request->discount_value,
            'max_discount' => $request->max_discount,
            'min_ride_amount' => $request->min_ride_amount ?? 0,
            'usage_limit' => $request->usage_limit,
            'expires_at' => $request->expires_at,
            'status' => $request->status,
        ]);

        return redirect()->route('admin.promotions.index')->with('success', "Promotion code {$promo->code} created.");
    }

    public function update(Request $request, $id)
    {
        $promo = Promotion::findOrFail($id);
        $request->validate([
            'code' => "required|string|unique:promotions,code,{$id}|max:20",
            'title' => 'required|string|max:100',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:1',
            'status' => 'required|in:active,expired,inactive',
        ]);

        $promo->update($request->all());

        return back()->with('success', "Promotion {$promo->code} updated.");
    }

    public function toggleStatus($id)
    {
        $promo = Promotion::findOrFail($id);
        $promo->status = ($promo->status === 'active') ? 'inactive' : 'active';
        $promo->save();

        return back()->with('success', "Promotion status changed to {$promo->status}.");
    }

    public function destroy($id)
    {
        $promo = Promotion::findOrFail($id);
        $promo->delete();

        return redirect()->route('admin.promotions.index')->with('success', "Promotion deleted.");
    }
}
