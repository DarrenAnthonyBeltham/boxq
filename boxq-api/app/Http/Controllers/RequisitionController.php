<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use App\Models\User;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use App\Mail\ManagerApprovalEmail;
use Carbon\Carbon;

class RequisitionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            $requisitions = Requisition::orderBy('created_at', 'desc')->get();
        } 
        elseif ($user->role === 'finance') {
            $requisitions = Requisition::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere(function ($subQ) {
                          $subQ->where('approval_stage', 'Finance Director')
                               ->orWhereIn('status', ['Approved', 'PO Created', 'Received', 'Processing Payment', 'Paid', 'Payment Failed']);
                      });
            })->orderBy('created_at', 'desc')->get();
        } 
        elseif ($user->role === 'manager') {
            $nowStr = Carbon::now()->format('Y-m-d');
            
            $delegators = User::where('delegated_to_id', $user->id)
                              ->whereNotNull('delegation_start')
                              ->where('delegation_start', '<=', $nowStr)
                              ->where('delegation_end', '>=', $nowStr)
                              ->pluck('department')
                              ->toArray();

            $departments = array_unique(array_merge([$user->department], $delegators));

            $requisitions = Requisition::where(function ($query) use ($departments, $user) {
                $query->where(function ($subQ) use ($departments) {
                    $subQ->whereIn('department', $departments)
                         ->where('status', '!=', 'Draft');
                })->orWhere('user_id', $user->id);
            })->orderBy('created_at', 'desc')->get();
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
            'status' => 'required|string|in:Pending,Draft',
            'has_tax' => 'nullable'
        ]);

        $hasTax = filter_var($request->input('has_tax', true), FILTER_VALIDATE_BOOLEAN);

        $subtotal = collect($validated['items'])->sum(function ($item) {
            return ($item['price'] ?? 0) * ($item['qty'] ?? 1);
        });

        $taxAmount = $hasTax ? ($subtotal * 0.11) : 0;
        $totalPrice = $subtotal + $taxAmount;

        $usdTotal = $validated['currency'] === 'IDR' ? ($totalPrice / $validated['exchange_rate']) : $totalPrice;
        
        $status = $validated['status'];
        $approvalStage = null;
        $approvalToken = null;
        $isOverBudget = false;

        if ($status === 'Pending') {
            $budget = Budget::where('department', $user->department)->first();
            
            if ($budget) {
                $currentSpent = Requisition::where('department', $user->department)
                    ->whereIn('status', ['Approved', 'PO Created', 'Received', 'Processing Payment', 'Paid'])
                    ->where('created_at', '>=', Carbon::now()->startOfMonth())
                    ->sum('total_price');

                if ($usdTotal > $budget->monthly_limit) {
                    return response()->json(['error' => 'Hard Block: This single request exceeds your entire monthly budget.'], 422);
                }

                if (($currentSpent + $usdTotal) > $budget->monthly_limit) {
                    $isOverBudget = true;
                }
            }

            if ($usdTotal < 500) {
                $status = 'Approved';
                $approvalStage = 'Completed';
            } else {
                $approvalStage = 'Manager';
                $approvalToken = Str::random(32);
            }
        }

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
            'subtotal' => $subtotal,
            'has_tax' => $hasTax,
            'tax_amount' => $taxAmount,
            'total_price' => $totalPrice,
            'currency' => $validated['currency'],
            'exchange_rate' => $validated['exchange_rate'],
            'cost_centers' => $validated['cost_centers'],
            'status' => $status,
            'approval_stage' => $approvalStage,
            'approval_token' => $approvalToken,
            'attachment' => $attachmentPath,
            'is_over_budget' => $isOverBudget
        ]);

        event(new \App\Events\RequisitionSubmitted($requisition));

        if ($approvalToken) {
            Mail::to('darrenanthonybeltham@gmail.com')->send(new ManagerApprovalEmail($requisition));
        }

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
            'status' => 'required|string|in:Pending,Draft',
            'has_tax' => 'nullable'
        ]);

        $hasTax = filter_var($request->input('has_tax', true), FILTER_VALIDATE_BOOLEAN);

        $subtotal = collect($validated['items'])->sum(function ($item) {
            return ($item['price'] ?? 0) * ($item['qty'] ?? 1);
        });

        $taxAmount = $hasTax ? ($subtotal * 0.11) : 0;
        $totalPrice = $subtotal + $taxAmount;

        $usdTotal = $validated['currency'] === 'IDR' ? ($totalPrice / $validated['exchange_rate']) : $totalPrice;
        
        $status = $validated['status'];
        $approvalStage = null;
        $approvalToken = null;
        $isOverBudget = false;

        if ($status === 'Pending') {
            $budget = Budget::where('department', $user->department)->first();
            
            if ($budget) {
                $currentSpent = Requisition::where('department', $user->department)
                    ->whereIn('status', ['Approved', 'PO Created', 'Received', 'Processing Payment', 'Paid'])
                    ->where('created_at', '>=', Carbon::now()->startOfMonth())
                    ->sum('total_price');

                if ($usdTotal > $budget->monthly_limit) {
                    return response()->json(['error' => 'Hard Block: This single request exceeds your entire monthly budget.'], 422);
                }

                if (($currentSpent + $usdTotal) > $budget->monthly_limit) {
                    $isOverBudget = true;
                }
            }

            if ($usdTotal < 500) {
                $status = 'Approved';
                $approvalStage = 'Completed';
            } else {
                $approvalStage = 'Manager';
                $approvalToken = Str::random(32);
            }
        }

        if ($request->hasFile('attachment')) {
            $requisition->attachment = $request->file('attachment')->store('attachments', 'public');
        }

        $requisition->justification = $validated['justification'] ?? '';
        $requisition->items = $validated['items'];
        $requisition->subtotal = $subtotal;
        $requisition->has_tax = $hasTax;
        $requisition->tax_amount = $taxAmount;
        $requisition->total_price = $totalPrice;
        $requisition->currency = $validated['currency'];
        $requisition->exchange_rate = $validated['exchange_rate'];
        $requisition->cost_centers = $validated['cost_centers'];
        $requisition->status = $status;
        $requisition->approval_stage = $approvalStage;
        $requisition->approval_token = $approvalToken;
        $requisition->is_over_budget = $isOverBudget;
        $requisition->save();

        if ($approvalToken) {
            Mail::to('darrenanthonybeltham@gmail.com')->send(new ManagerApprovalEmail($requisition));
        }

        return response()->json($requisition);
    }

    public function show(Request $request, $id)
    {
        $requisition = Requisition::findOrFail($id);
        
        if ($requisition->cost_centers && is_string($requisition->cost_centers)) {
            $requisition->cost_centers = json_decode($requisition->cost_centers, true);
        }

        return response()->json($requisition);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'status' => 'required|string|in:Approved,Rejected,Paid',
            'reason' => 'nullable|string|max:1000'
        ]);

        $requisition = Requisition::findOrFail($id);
        
        $usdTotal = $requisition->currency === 'IDR' ? ($requisition->total_price / $requisition->exchange_rate) : $requisition->total_price;

        if ($validated['status'] === 'Rejected') {
            $requisition->status = 'Rejected';
            $requisition->approval_stage = 'Rejected';
            $requisition->approval_token = null;
            $requisition->reason = $validated['reason'] ?? 'Rejected by ' . $user->name;
            $requisition->save();
            return response()->json($requisition);
        }

        if ($validated['status'] === 'Paid') {
            if (!in_array($user->role, ['finance', 'admin', 'manager'])) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            if (empty($requisition->invoice_attachment) || empty($requisition->vendor_account_number)) {
                return response()->json(['message' => 'Backend Security Block: Missing invoice or vendor bank details.'], 422);
            }

            $idrAmount = $requisition->currency === 'IDR' 
                ? ($requisition->invoice_amount ?? $requisition->total_price) 
                : (($requisition->invoice_amount ?? $requisition->total_price) * $requisition->exchange_rate);

            $xenditSecretKey = env('XENDIT_SECRET_KEY');
            
            $response = Http::withBasicAuth($xenditSecretKey, '')
                ->post('https://api.xendit.co/disbursements', [
                    'external_id' => 'req_' . $requisition->id . '_' . time(),
                    'bank_code' => $requisition->vendor_bank_code,
                    'account_holder_name' => $requisition->vendor_account_name,
                    'account_number' => $requisition->vendor_account_number,
                    'description' => 'Payment for Requisition: ' . Str::limit($requisition->justification, 50),
                    'amount' => (int) round($idrAmount)
                ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Payment Gateway Error: ' . $response->json('message', 'Failed to process payment with Xendit.')
                ], 422);
            }

            $xenditData = $response->json();
            $requisition->xendit_disbursement_id = $xenditData['id'] ?? null;
            $requisition->status = 'Processing Payment';
            $requisition->paid_by = $user->name;
            $requisition->paid_at = Carbon::now()->toIso8601String();
            
            if (isset($validated['reason']) && !empty($validated['reason'])) {
                $requisition->reason = $validated['reason'];
            }
            
            $requisition->save();
            return response()->json($requisition);
        }

        if ($validated['status'] === 'Approved') {
            if ($requisition->approval_stage === 'Manager') {
                if ($usdTotal > 5000) {
                    $requisition->approval_stage = 'VP';
                    $requisition->approval_token = Str::random(32); 
                } else {
                    $requisition->status = 'Approved';
                    $requisition->approval_stage = 'Completed';
                    $requisition->approval_token = null;
                }
            } 
            elseif ($requisition->approval_stage === 'VP') {
                if ($user->role !== 'admin') {
                    return response()->json(['message' => 'VP (Admin) required'], 403);
                }
                $requisition->approval_stage = 'Finance Director';
                $requisition->approval_token = Str::random(32);
            } 
            elseif ($requisition->approval_stage === 'Finance Director') {
                if (!in_array($user->role, ['finance', 'admin', 'manager'])) {
                    return response()->json(['message' => 'Finance Director required'], 403);
                }
                $requisition->status = 'Approved';
                $requisition->approval_stage = 'Completed';
                $requisition->approval_token = null;
            }

            if (isset($validated['reason'])) {
                $requisition->reason = $validated['reason'];
            }

            $requisition->save();

            if ($requisition->approval_token) {
                Mail::to('darrenanthonybeltham@gmail.com')->send(new ManagerApprovalEmail($requisition));
            }
        }

        return response()->json($requisition);
    }

    public function uploadInvoice(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role, ['finance', 'admin', 'manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'invoice' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'invoice_amount' => 'required|numeric|min:0',
            'vendor_bank_code' => 'required|string',
            'vendor_account_number' => 'required|string',
            'vendor_account_name' => 'required|string'
        ]);

        $requisition = Requisition::findOrFail($id);
        $requisition->invoice_attachment = $request->file('invoice')->store('invoices', 'public');
        $requisition->invoice_amount = (float) $request->input('invoice_amount');
        $requisition->vendor_bank_code = $request->input('vendor_bank_code');
        $requisition->vendor_account_number = $request->input('vendor_account_number');
        $requisition->vendor_account_name = $request->input('vendor_account_name');
        $requisition->save();

        return response()->json($requisition);
    }
}