<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GoodsReceiptController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Requisition::whereIn('status', ['PO Created', 'Received']);

        if (!in_array($user->role, ['admin', 'finance'])) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('requester', 'like', "%{$searchTerm}%")
                  ->orWhere('department', 'like', "%{$searchTerm}%")
                  ->orWhere('vendor_account_name', 'like', "%{$searchTerm}%");
            });
        }

        $receipts = $query->orderBy('updated_at', 'desc')->paginate(15);

        $receipts->getCollection()->transform(function ($req) {
            if (is_string($req->items)) {
                $req->items = json_decode($req->items, true);
            }
            return $req;
        });

        return response()->json($receipts);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'requisition_id' => 'required',
            'delivery_notes' => 'nullable|string|max:500'
        ]);

        $requisition = Requisition::findOrFail($validated['requisition_id']);

        if (!in_array($user->role, ['admin', 'finance']) && $requisition->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. Only the original requester can confirm receipt.'], 403);
        }

        if ($requisition->status !== 'PO Created') {
            return response()->json(['message' => 'Invalid status. Goods can only be received after a PO is created.'], 422);
        }

        $safeNotes = strip_tags($validated['delivery_notes'] ?? '');

        $requisition->status = 'Received';
        if ($safeNotes) {
            $requisition->reason = $safeNotes;
        }
        $requisition->save();

        Notification::create([
            'user_id' => $requisition->user_id,
            'title' => 'Items Received',
            'message' => 'You have successfully confirmed delivery. Finance has been notified.',
            'type' => 'success',
            'link' => '/requisitions/' . $requisition->id,
            'is_read' => false
        ]);

        $financeUsers = User::where('role', 'finance')->get();
        foreach ($financeUsers as $finance) {
            Notification::create([
                'user_id' => $finance->id,
                'title' => 'Safe to Pay: GRN Confirmed',
                'message' => "{$requisition->requester} confirmed receipt of goods. Ready for invoice matching.",
                'type' => 'info',
                'link' => "/requisitions/{$requisition->id}",
                'is_read' => false
            ]);
        }

        return response()->json([
            'message' => 'Goods marked as received securely.',
            'requisition' => $requisition
        ]);
    }
}