<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PurchaseOrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'requisition_id' => 'required|string',
            'vendor_id' => 'required|string'
        ]);

        $requisition = Requisition::findOrFail($validated['requisition_id']);
        $vendor = Vendor::findOrFail($validated['vendor_id']);

        $count = PurchaseOrder::count() + 1;
        $poNumber = 'PO-' . date('Y') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        $po = PurchaseOrder::create([
            'po_number' => $poNumber,
            'requisition_id' => $requisition->id,
            'vendor_id' => $vendor->id,
            'status' => 'Generated',
            'total_amount' => $requisition->total_price,
            'pdf_url' => ''
        ]);

        $pdf = Pdf::loadView('pdf.po', [
            'po' => $po,
            'requisition' => $requisition,
            'vendor' => $vendor
        ]);

        $fileName = $poNumber . '.pdf';
        Storage::disk('public')->put('pos/' . $fileName, $pdf->output());

        $po->pdf_url = '/storage/pos/' . $fileName;
        $po->save();

        $requisition->status = 'PO Created';
        $requisition->save();

        return response()->json($po, 201);
    }

    public function index()
    {
        return response()->json(PurchaseOrder::orderBy('created_at', 'desc')->get());
    }
}