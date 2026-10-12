<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $priority = $request->query('priority', 'all');

        $query = Complaint::with(['customer', 'driver', 'ride'])->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($priority !== 'all') {
            $query->where('priority', $priority);
        }

        $complaints = $query->paginate(15)->withQueryString();

        $totalTickets = Complaint::count();
        $openTickets = Complaint::where('status', 'open')->count();
        $investigatingTickets = Complaint::where('status', 'investigating')->count();
        $resolvedTickets = Complaint::where('status', 'resolved')->count();

        return view('admin.complaints.index', compact(
            'complaints',
            'status',
            'priority',
            'totalTickets',
            'openTickets',
            'investigatingTickets',
            'resolvedTickets'
        ));
    }

    public function show($id)
    {
        $complaint = Complaint::with(['customer', 'driver', 'ride', 'replies'])->findOrFail($id);
        return view('admin.complaints.show', compact('complaint'));
    }

    public function reply(Request $request, $id)
    {
        $complaint = Complaint::findOrFail($id);
        $request->validate(['message' => 'required|string']);

        ComplaintReply::create([
            'complaint_id' => $complaint->id,
            'sender_type' => 'admin',
            'sender_id' => Auth::id() ?? 1,
            'sender_name' => Auth::user()->name ?? 'Alo Support Executive',
            'message' => $request->message,
        ]);

        if ($complaint->status === 'open') {
            $complaint->status = 'investigating';
            $complaint->save();
        }

        return back()->with('success', 'Response sent to customer successfully.');
    }

    public function updateStatus(Request $request, $id)
    {
        $complaint = Complaint::findOrFail($id);
        $request->validate(['status' => 'required|in:open,investigating,resolved,rejected']);

        $complaint->status = $request->status;
        if ($request->status === 'resolved') {
            $complaint->resolved_at = now();
        }
        $complaint->save();

        return back()->with('success', "Ticket status updated to {$complaint->status}.");
    }
}
