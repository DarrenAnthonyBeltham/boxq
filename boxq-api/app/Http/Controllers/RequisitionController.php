<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use Illuminate\Http\Request;

class RequisitionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            $requisitions = Requisition::orderBy('created_at', 'desc')->get();
        } 
        elseif ($user->role === 'finance') {
            $requisitions = Requisition::where('status', 'Approved')
                                       ->orderBy('created_at', 'desc')
                                       ->get();
        } 
        elseif ($user->role === 'manager') {
            $requisitions = Requisition::where(function ($query) use ($user) {
                $query->where('department', $user->department)
                      ->where('status', '!=', 'Draft');
            })->orWhere('user_id', $user->id)
              ->orderBy('created_at', 'desc')
              ->get();
        } 
        else {
            $requisitions = Requisition::where('user_id', $user->id)
                                       ->orderBy('created_at', 'desc')
                                       ->get();
        }

        return response()->json($requisitions);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $isDraft = $request->input('status') === 'Draft';

        $validated = $request->validate([
            'justification' => $isDraft ? 'nullable|string|max:1000' : 'required|string|min:10|max:1000',
            'items' => 'required|array|min:1',
            'items.*.name' => $isDraft ? 'nullable|string' : 'required|string',
            'items.*.price' => $isDraft ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
            'items.*.qty' => $isDraft ? 'nullable|integer|min:1' : 'required|integer|min:1',
            'currency' => 'required|string|in:USD,IDR',
            'exchange_rate' => 'required|numeric|min:1',
            'cost_centers' => 'required|array|min:1',
            'cost_centers.*.department' => 'required|string',
            'cost_centers.*.percentage' => 'required|numeric|min:1|max:100',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'status' => 'required|string|in:Pending,Draft'
        ]);

        $totalPrice = collect($validated['items'])->sum(function ($item) {
            return ($item['price'] ?? 0) * ($item['qty'] ?? 1);
        });

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('attachments', 'public');
        }

        $requisition = Requisition::create([
            'user_id' => $user->id,
            'requester' => $user->name,
            'department' => $user->department,
            'justification' => $validated['justification'] ?? '',
            'items' => $validated['items'],
            'total_price' => $totalPrice,
            'currency' => $validated['currency'],
            'exchange_rate' => $validated['exchange_rate'],
            'cost_centers' => $validated['cost_centers'],
            'status' => $validated['status'],
            'attachment' => $attachmentPath,
        ]);

        return response()->json($requisition, 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $requisition = Requisition::findOrFail($id);

        if ($requisition->user_id !== $user->id || $requisition->status !== 'Draft') {
            return response()->json(['message' => 'Unauthorized or not a draft'], 403);
        }

        $isDraft = $request->input('status') === 'Draft';

        $validated = $request->validate([
            'justification' => $isDraft ? 'nullable|string|max:1000' : 'required|string|min:10|max:1000',
            'items' => 'required|array|min:1',
            'items.*.name' => $isDraft ? 'nullable|string' : 'required|string',
            'items.*.price' => $isDraft ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
            'items.*.qty' => $isDraft ? 'nullable|integer|min:1' : 'required|integer|min:1',
            'currency' => 'required|string|in:USD,IDR',
            'exchange_rate' => 'required|numeric|min:1',
            'cost_centers' => 'required|array|min:1',
            'cost_centers.*.department' => 'required|string',
            'cost_centers.*.percentage' => 'required|numeric|min:1|max:100',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'status' => 'required|string|in:Pending,Draft'
        ]);

        $totalPrice = collect($validated['items'])->sum(function ($item) {
            return ($item['price'] ?? 0) * ($item['qty'] ?? 1);
        });

        if ($request->hasFile('attachment')) {
            $requisition->attachment = $request->file('attachment')->store('attachments', 'public');
        }

        $requisition->justification = $validated['justification'] ?? '';
        $requisition->items = $validated['items'];
        $requisition->total_price = $totalPrice;
        $requisition->currency = $validated['currency'];
        $requisition->exchange_rate = $validated['exchange_rate'];
        $requisition->cost_centers = $validated['cost_centers'];
        $requisition->status = $validated['status'];
        $requisition->save();

        return response()->json($requisition);
    }

    public function show(Request $request, $id)
    {
        $requisition = Requisition::findOrFail($id);
        $user = $request->user();

        if ($user->role === 'employee' && $requisition->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'manager' && $requisition->department !== $user->department && $requisition->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($requisition);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'status' => 'required|string|in:Approved,Rejected,Paid',
            'reason' => 'nullable|string|max:500'
        ]);

        $requisition = Requisition::findOrFail($id);

        if ($user->role === 'employee') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'manager' && $requisition->department !== $user->department) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($validated['status'] === 'Paid' && $user->role !== 'finance' && $user->role !== 'admin') {
            return response()->json(['message' => 'Only Finance can mark as Paid'], 403);
        }

        $requisition->status = $validated['status'];
        
        if (isset($validated['reason'])) {
            $requisition->reason = $validated['reason'];
        }

        $requisition->save();

        return response()->json($requisition);
    }
}