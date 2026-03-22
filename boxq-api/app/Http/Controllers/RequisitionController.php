<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use App\Models\User;
use App\Models\Budget;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use App\Mail\ManagerApprovalEmail;
use Barryvdh\DomPDF\Facade\Pdf;
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
                               ->orWhereIn('status', ['Approved', 'PO Created', 'Received', 'Processing Payment', 'Partially Paid', 'Paid', 'Payment Failed', 'Reconciled']);
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
            'exchange_rate' => 'nullable|numeric',
            'cost_centers' => 'required|array|min:1',
            'cost_centers.*.department' => 'required|string',
            'cost_centers.*.percentage' => 'required|numeric|min:1|max:100',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'status' => 'required|string|in:Pending,Draft',
            'has_tax' => 'nullable'
        ]);

        $safeJustification = strip_tags($validated['justification'] ?? '');

        if ($validated['currency'] === 'USD' && (empty($validated['exchange_rate']) || $validated['exchange_rate'] <= 1)) {
            $fxResponse = Http::get('https://api.exchangerate-api.com/v4/latest/USD');
            $validated['exchange_rate'] = $fxResponse->successful() ? $fxResponse->json('rates.IDR') : 15500;
        } else {
            $validated['exchange_rate'] = $validated['exchange_rate'] ?? 1;
        }

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
                    ->whereIn('status', ['Approved', 'PO Created', 'Received', 'Processing Payment', 'Partially Paid', 'Paid', 'Reconciled'])
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
            'justification' => $safeJustification,
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
            'is_over_budget' => $isOverBudget,
            'amount_paid' => 0
        ]);

        if ($status !== 'Draft') {
            $this->dispatchNewRequisitionNotifications($requisition, $user);
        }

        if ($approvalToken) {
            Mail::to('darrenanthonybeltham@gmail.com')->queue(new ManagerApprovalEmail($requisition));
        }

        return response()->json($requisition, 201);
    }

    private function dispatchNewRequisitionNotifications($requisition, $requester)
    {
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => 'New Requisition Created',
                'message' => "{$requester->name} ({$requester->department}) requested {$requisition->currency} " . number_format($requisition->total_price),
                'type' => 'info',
                'link' => "/requisitions/{$requisition->id}",
                'is_read' => false
            ]);
        }

        if ($requisition->approval_stage === 'Manager') {
            $managers = User::where('role', 'manager')->where('department', $requester->department)->get();
            foreach ($managers as $manager) {
                Notification::create([
                    'user_id' => $manager->id,
                    'title' => 'Action Required: Manager Approval',
                    'message' => "A request from {$requester->name} requires your review.",
                    'type' => 'warning',
                    'link' => "/requisitions/{$requisition->id}",
                    'is_read' => false
                ]);
            }
        } 
        elseif ($requisition->status === 'Approved') {
            $financeUsers = User::where('role', 'finance')->get();
            foreach ($financeUsers as $finance) {
                Notification::create([
                    'user_id' => $finance->id,
                    'title' => 'Auto-Approved Request',
                    'message' => "A request from {$requester->department} was auto-approved and needs PO processing.",
                    'type' => 'success',
                    'link' => "/requisitions/{$requisition->id}",
                    'is_read' => false
                ]);
            }
        }
    }

    public function downloadPoPdf($id)
    {
        $requisition = Requisition::findOrFail($id);

        $po = (object) [
            'po_number' => 'PO-' . strtoupper(substr($requisition->id, -8)),
            'created_at' => $requisition->created_at,
            'total_amount' => $requisition->total_price,
        ];

        $vendor = (object) [
            'name' => $requisition->vendor_account_name ?? 'N/A',
            'address' => $requisition->vendor_address ?? 'N/A',
            'email' => $requisition->vendor_email ?? 'N/A',
            'tax_id' => $requisition->vendor_tax_id ?? 'N/A',
            'payment_terms' => $requisition->vendor_payment_terms ?? 'N/A',
        ];

        $pdf = Pdf::loadView('pdf.po', compact('requisition', 'po', 'vendor'));
        
        $filename = $po->po_number . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
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
            'exchange_rate' => 'nullable|numeric',
            'cost_centers' => 'required|array|min:1',
            'cost_centers.*.department' => 'required|string',
            'cost_centers.*.percentage' => 'required|numeric|min:1|max:100',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'status' => 'required|string|in:Pending,Draft',
            'has_tax' => 'nullable'
        ]);

        $safeJustification = strip_tags($validated['justification'] ?? '');

        if ($validated['currency'] === 'USD' && (empty($validated['exchange_rate']) || $validated['exchange_rate'] <= 1)) {
            $fxResponse = Http::get('https://api.exchangerate-api.com/v4/latest/USD');
            $validated['exchange_rate'] = $fxResponse->successful() ? $fxResponse->json('rates.IDR') : 15500;
        } else {
            $validated['exchange_rate'] = $validated['exchange_rate'] ?? 1;
        }

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
                    ->whereIn('status', ['Approved', 'PO Created', 'Received', 'Processing Payment', 'Partially Paid', 'Paid', 'Reconciled'])
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

        $requisition->justification = $safeJustification;
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

        if ($status !== 'Draft') {
            $this->dispatchNewRequisitionNotifications($requisition, $user);
        }

        if ($approvalToken) {
            Mail::to('darrenanthonybeltham@gmail.com')->queue(new ManagerApprovalEmail($requisition));
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
            'status' => 'required|string|in:Approved,Rejected,Processing Payment,Reconciled',
            'reason' => 'nullable|string|max:1000',
            'payment_amount' => 'nullable|numeric|min:1'
        ]);

        $requisition = Requisition::findOrFail($id);

        if (in_array($validated['status'], ['Approved', 'Rejected'])) {
            $isAuthorized = false;
            if ($user->role === 'admin') {
                $isAuthorized = true;
            } elseif ($user->role === 'manager' && $requisition->approval_stage === 'Manager') {
                $isAuthorized = true;
            } elseif ($user->role === 'finance' && $requisition->approval_stage === 'Finance Director') {
                $isAuthorized = true;
            }

            if (!$isAuthorized) {
                return response()->json(['message' => 'Unauthorized. You do not have permission for this stage.'], 403);
            }
        }
        
        $usdTotal = $requisition->currency === 'IDR' ? ($requisition->total_price / $requisition->exchange_rate) : $requisition->total_price;
        $safeReason = strip_tags($validated['reason'] ?? '');

        if ($validated['status'] === 'Reconciled') {
            if (!in_array($user->role, ['finance', 'admin'])) {
                return response()->json(['message' => 'Unauthorized. Finance or Admin required.'], 403);
            }
            $requisition->status = 'Reconciled';
            $requisition->reconciled_by = $user->name;
            $requisition->reconciled_at = Carbon::now()->toIso8601String();
            $requisition->save();

            Notification::create([
                'user_id' => $requisition->user_id,
                'title' => 'Requisition Fully Reconciled',
                'message' => 'Your request lifecycle is complete and reconciled.',
                'type' => 'success',
                'link' => '/requisitions/' . $requisition->id,
                'is_read' => false
            ]);

            return response()->json($requisition);
        }

        if ($validated['status'] === 'Rejected') {
            $requisition->status = 'Rejected';
            $requisition->approval_stage = 'Rejected';
            $requisition->approval_token = null;
            $requisition->reason = $safeReason ?: 'Rejected by ' . $user->name;
            $requisition->save();

            Notification::create([
                'user_id' => $requisition->user_id,
                'title' => 'Requisition Rejected',
                'message' => 'Your request was rejected by ' . $user->name,
                'type' => 'danger',
                'link' => '/requisitions/' . $requisition->id,
                'is_read' => false
            ]);

            return response()->json($requisition);
        }

        if ($validated['status'] === 'Processing Payment') {
            if (!in_array($user->role, ['finance', 'admin'])) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            if (empty($requisition->invoice_attachment) || empty($requisition->vendor_account_number)) {
                return response()->json(['message' => 'Backend Security Block: Missing invoice or vendor bank details.'], 422);
            }

            $amountToPayIDR = (int) round($request->input('payment_amount'));

            $xenditSecretKey = env('XENDIT_SECRET_KEY');
            
            $response = Http::withBasicAuth($xenditSecretKey, '')
                ->post('https://api.xendit.co/disbursements', [
                    'external_id' => 'req_' . $requisition->id . '_' . time(),
                    'bank_code' => $requisition->vendor_bank_code,
                    'account_holder_name' => $requisition->vendor_account_name,
                    'account_number' => $requisition->vendor_account_number,
                    'description' => 'Payment for Requisition: ' . Str::limit($requisition->justification, 50),
                    'amount' => $amountToPayIDR
                ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Payment Gateway Error: ' . $response->json('message', 'Failed to process payment with Xendit.')
                ], 422);
            }

            $xenditData = $response->json();
            $requisition->xendit_disbursement_id = $xenditData['id'] ?? null;
            $requisition->amount_paid = ($requisition->amount_paid ?? 0) + $amountToPayIDR;
            $requisition->status = 'Processing Payment';
            $requisition->paid_by = $user->name;
            $requisition->paid_at = Carbon::now()->toIso8601String();
            
            if (!empty($safeReason)) {
                $requisition->reason = $safeReason;
            }
            
            $requisition->save();

            Notification::create([
                'user_id' => $requisition->user_id,
                'title' => 'Payment Initiated',
                'message' => 'Funds are currently being transferred to the vendor.',
                'type' => 'info',
                'link' => '/requisitions/' . $requisition->id,
                'is_read' => false
            ]);

            return response()->json($requisition);
        }

        if ($validated['status'] === 'Approved') {
            if ($requisition->approval_stage === 'Manager') {
                if ($usdTotal > 5000) {
                    $requisition->approval_stage = 'VP';
                    $requisition->approval_token = Str::random(32); 

                    $admins = User::where('role', 'admin')->get();
                    foreach ($admins as $admin) {
                        Notification::create([
                            'user_id' => $admin->id,
                            'title' => 'VP Approval Required',
                            'message' => "High-value request from {$requisition->department} requires Admin review.",
                            'type' => 'warning',
                            'link' => "/requisitions/{$requisition->id}",
                            'is_read' => false
                        ]);
                    }

                } else {
                    $requisition->status = 'Approved';
                    $requisition->approval_stage = 'Completed';
                    $requisition->approval_token = null;
                }
            } 
            elseif ($requisition->approval_stage === 'VP') {
                $requisition->approval_stage = 'Finance Director';
                $requisition->approval_token = Str::random(32);

                $financeUsers = User::where('role', 'finance')->get();
                foreach ($financeUsers as $finance) {
                    Notification::create([
                        'user_id' => $finance->id,
                        'title' => 'Finance Director Approval Required',
                        'message' => "High-value request requires final financial approval.",
                        'type' => 'warning',
                        'link' => "/requisitions/{$requisition->id}",
                        'is_read' => false
                    ]);
                }

            } 
            elseif ($requisition->approval_stage === 'Finance Director') {
                $requisition->status = 'Approved';
                $requisition->approval_stage = 'Completed';
                $requisition->approval_token = null;
            }

            if (!empty($safeReason)) {
                $requisition->reason = $safeReason;
            }

            $requisition->save();

            if ($requisition->approval_stage === 'Completed') {
                Notification::create([
                    'user_id' => $requisition->user_id,
                    'title' => 'Request Approved!',
                    'message' => 'Your requisition has been fully approved.',
                    'type' => 'success',
                    'link' => '/requisitions/' . $requisition->id,
                    'is_read' => false
                ]);

                $financeUsers = User::where('role', 'finance')->get();
                foreach ($financeUsers as $finance) {
                    Notification::create([
                        'user_id' => $finance->id,
                        'title' => 'Ready for Processing',
                        'message' => "Request from {$requisition->department} is approved and needs PO/Payment.",
                        'type' => 'info',
                        'link' => "/requisitions/{$requisition->id}",
                        'is_read' => false
                    ]);
                }
            }

            if ($requisition->approval_token) {
                Mail::to('darrenanthonybeltham@gmail.com')->queue(new ManagerApprovalEmail($requisition));
            }
        }

        return response()->json($requisition);
    }

    public function uploadInvoice(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role, ['finance', 'admin'])) {
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