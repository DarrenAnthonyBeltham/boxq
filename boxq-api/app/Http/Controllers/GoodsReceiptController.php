<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\Requisition;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function index()
    {
        return response()->json(GoodsReceipt::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'requisition_id' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        $requisition = Requisition::findOrFail($validated['requisition_id']);

        $count = GoodsReceipt::count() + 1;
        $grnNumber = 'GRN-' . date('Y') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        $user = $request->user();

        $grn = GoodsReceipt::create([
            'grn_number' => $grnNumber,
            'requisition_id' => $requisition->id,
            'received_by' => $user ? $user->name : 'System',
            'notes' => $validated['notes'] ?? '',
            'status' => 'Completed'
        ]);

        $requisition->status = 'Received';
        $requisition->save();

        return response()->json($grn, 201);
    }
}