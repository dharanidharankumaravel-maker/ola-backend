<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\Driver;
use Illuminate\Http\Request;

class FinancialController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'payouts');

        // Metrics
        $totalRevenue = Ride::where('status', 'completed')->sum('total_fare');
        $platformCommission = Ride::where('status', 'completed')->sum('platform_fee');
        $pendingPayoutsSum = Payout::where('status', 'pending')->sum('amount');
        $totalCompletedTrips = Ride::where('status', 'completed')->count();

        // Payouts list
        $payouts = Payout::with('driver')->latest()->paginate(10, ['*'], 'payout_page');

        // Payments list
        $payments = Payment::with(['customer', 'ride'])->latest()->paginate(10, ['*'], 'payment_page');

        return view('admin.financials.index', compact(
            'tab',
            'totalRevenue',
            'platformCommission',
            'pendingPayoutsSum',
            'totalCompletedTrips',
            'payouts',
            'payments'
        ));
    }

    public function processPayout(Request $request, $id)
    {
        $payout = Payout::findOrFail($id);
        $request->validate([
            'status' => 'required|in:paid,rejected',
            'transaction_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $payout->status = $request->status;
        $payout->transaction_reference = $request->transaction_reference;
        $payout->notes = $request->notes;
        if ($request->status === 'paid') {
            $payout->processed_at = now();
        }
        $payout->save();

        return back()->with('success', "Payout #{$payout->payout_number} marked as {$payout->status}.");
    }
}
