<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VendorPortalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'vendor') {
            return response()->json(['message' => 'Unauthorized. Vendor access only.'], 403);
        }

        $query = Requisition::where('vendor_email', $user->email)
                            ->whereIn('status', ['PO Created', 'Received', 'Processing Payment', 'Partially Paid', 'Paid', 'Reconciled']);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('id', 'like', "%{$searchTerm}%")
                  ->orWhere('_id', 'like', "%{$searchTerm}%");
            });
        }

        $orders = $query->orderBy('updated_at', 'desc')->paginate(10);

        $orders->getCollection()->transform(function ($req) {
            if (is_string($req->items)) {
                $req->items = json_decode($req->items, true);
            }
            return $req;
        });

        return response()->json($orders);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        if ($user->role !== 'vendor') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $requisition = Requisition::findOrFail($id);

        if ($requisition->vendor_email !== $user->email) {
            return response()->json(['message' => 'Unauthorized. This PO belongs to another vendor.'], 403);
        }

        if (is_string($requisition->items)) {
            $requisition->items = json_decode($requisition->items, true);
        }

        return response()->json($requisition);
    }

    public function uploadInvoice(Request $request, $id)
    {
        $user = $request->user();
        
        if ($user->role !== 'vendor') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $requisition = Requisition::findOrFail($id);

        if ($requisition->vendor_email !== $user->email) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'invoice' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'invoice_amount' => 'required|numeric|min:0',
            'vendor_bank_code' => 'required|string',
            'vendor_account_number' => 'required|string',
            'vendor_account_name' => 'required|string'
        ]);

        $requisition->invoice_attachment = $request->file('invoice')->store('invoices');
        $requisition->invoice_amount = (float) $request->input('invoice_amount');
        $requisition->vendor_bank_code = $request->input('vendor_bank_code');
        $requisition->vendor_account_number = $request->input('vendor_account_number');
        $requisition->vendor_account_name = $request->input('vendor_account_name');
        
        $requisition->save();

        AuditLog::create([
            'requisition_id' => (string) $requisition->id,
            'user_id' => $user->id,
            'user_name' => $user->name . ' (Vendor)',
            'ip_address' => $request->ip(),
            'action' => 'Vendor Uploaded Invoice',
            'changes' => [
                'invoice_amount' => ['old' => null, 'new' => $requisition->invoice_amount]
            ]
        ]);

        return response()->json($requisition);
    }
}