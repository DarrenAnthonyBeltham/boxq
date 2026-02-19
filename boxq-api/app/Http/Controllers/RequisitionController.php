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
            $requisitions = Requisition::where('department', $user->department)
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

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $totalPrice = collect($validated['items'])->sum(function ($item) {
            return $item['price'] * $item['qty'];
        });

        $requisition = Requisition::create([
            'user_id' => $user->id,
            'requester' => $user->name,
            'department' => $user->department,
            'items' => $validated['items'],
            'total_price' => $totalPrice,
            'status' => 'Pending',
        ]);

        return response()->json($requisition, 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'status' => 'required|string|in:Approved,Rejected,Paid'
        ]);

        $requisition = Requisition::findOrFail($id);

        if ($user->role === 'employee') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'manager' && $requisition->department !== $user->department) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $requisition->status = $validated['status'];
        $requisition->save();

        return response()->json($requisition);
    }
}