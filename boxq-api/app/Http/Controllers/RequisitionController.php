<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\ManagerApprovalEmail;

class RequisitionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            $requisitions = Requisition::orderBy('created_at', 'desc')->get();
        } 
        elseif ($user->role === 'finance') {
            $requisitions = Requisition::whereIn('status', ['Pending', 'Approved'])
                                       ->where(function ($q) {
                                            $q->where('approval_stage', 'Finance Director')
                                              ->orWhere('status', 'Approved');
                                       })
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

        $usdTotal = $validated['currency'] === 'IDR' ? ($totalPrice / $validated['exchange_rate']) : $totalPrice;
        
        $status = $validated['status'];
        $approvalStage = null;
        $approvalToken = null;

        if ($status === 'Pending') {
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
            'total_price' => $totalPrice,
            'currency' => $validated['currency'],
            'exchange_rate' => $validated['exchange_rate'],
            'cost_centers' => $validated['cost_centers'],
            'status' => $status,
            'approval_stage' => $approvalStage,
            'approval_token' => $approvalToken,
            'attachment' => $attachmentPath,
        ]);

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
            'status' => 'required|string|in:Pending,Draft'
        ]);

        $totalPrice = collect($validated['items'])->sum(function ($item) {
            return ($item['price'] ?? 0) * ($item['qty'] ?? 1);
        });

        $usdTotal = $validated['currency'] === 'IDR' ? ($totalPrice / $validated['exchange_rate']) : $totalPrice;
        
        $status = $validated['status'];
        $approvalStage = null;
        $approvalToken = null;

        if ($status === 'Pending') {
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
        $requisition->total_price = $totalPrice;
        $requisition->currency = $validated['currency'];
        $requisition->exchange_rate = $validated['exchange_rate'];
        $requisition->cost_centers = $validated['cost_centers'];
        $requisition->status = $status;
        $requisition->approval_stage = $approvalStage;
        $requisition->approval_token = $approvalToken;
        $requisition->save();

        if ($approvalToken) {
            Mail::to('darrenanthonybeltham@gmail.com')->send(new ManagerApprovalEmail($requisition));
        }

        return response()->json($requisition);
    }

    public function show(Request $request, $id)
    {
        $requisition = Requisition::findOrFail($id);
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
            if ($user->role !== 'finance' && $user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            $requisition->status = 'Paid';
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
                if ($user->role !== 'finance' && $user->role !== 'admin') {
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

    public function emailApproval(Request $request, $id)
    {
        $token = $request->query('token');
        $action = $request->query('action');

        if (!$token || !in_array($action, ['approve', 'reject'])) {
            return response('Invalid request parameters.', 400);
        }

        $requisition = Requisition::where('_id', $id)->orWhere('id', $id)->first();

        if (!$requisition || $requisition->approval_token !== $token) {
            return response('Invalid or expired approval link.', 403);
        }

        if ($action === 'reject') {
            $requisition->status = 'Rejected';
            $requisition->approval_stage = 'Rejected';
            $requisition->approval_token = null;
            $requisition->reason = 'Rejected via Email Link';
            $requisition->save();
            return response('Requisition has been successfully REJECTED.', 200);
        }

        $usdTotal = $requisition->currency === 'IDR' ? ($requisition->total_price / $requisition->exchange_rate) : $requisition->total_price;

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
            $requisition->approval_stage = 'Finance Director';
            $requisition->approval_token = Str::random(32);
        } 
        elseif ($requisition->approval_stage === 'Finance Director') {
            $requisition->status = 'Approved';
            $requisition->approval_stage = 'Completed';
            $requisition->approval_token = null;
        }

        $requisition->save();

        if ($requisition->approval_token) {
            Mail::to('darrenanthonybeltham@gmail.com')->send(new ManagerApprovalEmail($requisition));
        }

        return response('Requisition successfully APPROVED. You may close this window.', 200);
    }
}