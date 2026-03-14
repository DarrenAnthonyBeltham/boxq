<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index()
    {
        return response()->json(Vendor::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'tax_id' => 'required|string',
            'payment_terms' => 'required|string',
            'email' => 'required|email',
            'address' => 'required|string',
        ]);

        $vendor = Vendor::create($validated);
        
        return response()->json($vendor, 201);
    }
}