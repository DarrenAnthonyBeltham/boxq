<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Requisition;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return response()->json(Budget::all());
    }

    public function current(Request $request)
    {
        $department = $request->user()->department;
        $budget = Budget::where('department', $department)->first();
        $limit = $budget ? $budget->monthly_limit : 0;

        $spent = Requisition::where('department', $department)
            ->whereIn('status', ['Approved', 'PO Created', 'Received', 'Paid'])
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('total_price');

        return response()->json([
            'limit' => $limit,
            'spent' => $spent,
            'remaining' => max(0, $limit - $spent)
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'department' => 'required|string',
            'monthly_limit' => 'required|numeric|min:0'
        ]);

        $budget = Budget::updateOrCreate(
            ['department' => $validated['department']],
            ['monthly_limit' => $validated['monthly_limit']]
        );

        return response()->json($budget);
    }
}