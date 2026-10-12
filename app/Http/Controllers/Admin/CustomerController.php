<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $search = $request->query('search');

        $query = Customer::latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('customer_code', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate(15)->withQueryString();

        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('status', 'active')->count();
        $blockedCustomers = Customer::where('status', 'blocked')->count();
        $totalSpent = Customer::sum('total_spent');

        return view('admin.customers.index', compact(
            'customers',
            'status',
            'search',
            'totalCustomers',
            'activeCustomers',
            'blockedCustomers',
            'totalSpent'
        ));
    }

    public function show($id)
    {
        $customer = Customer::with(['rides' => function ($q) {
            $q->latest()->take(10);
        }, 'payments' => function ($q) {
            $q->latest()->take(10);
        }, 'complaints'])->findOrFail($id);

        return view('admin.customers.show', compact('customer'));
    }

    public function toggleBlock($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->status = ($customer->status === 'blocked') ? 'active' : 'blocked';
        $customer->save();

        $msg = $customer->status === 'blocked' ? 'blocked' : 'unblocked';
        return back()->with('success', "Customer {$customer->name} has been {$msg}.");
    }

    public function adjustWallet(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $request->validate([
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        $amount = (float)$request->amount;
        if ($request->type === 'credit') {
            $customer->wallet_balance += $amount;
        } else {
            $customer->wallet_balance = max(0, $customer->wallet_balance - $amount);
        }
        $customer->save();

        Payment::create([
            'payment_number' => 'WAL-' . strtoupper(Str::random(6)),
            'customer_id' => $customer->id,
            'amount' => $amount,
            'payment_method' => 'wallet',
            'transaction_id' => 'ADMIN_ADJ_' . strtoupper(Str::random(8)),
            'status' => 'completed',
        ]);

        return back()->with('success', "Wallet adjusted by {$request->amount} successfully.");
    }
}
