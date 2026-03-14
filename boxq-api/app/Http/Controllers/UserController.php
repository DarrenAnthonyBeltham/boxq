<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::all();
        return response()->json($users);
    }

    public function setDelegation(Request $request)
    {
        $validated = $request->validate([
            'delegated_to_id' => 'nullable|string',
            'delegation_start' => 'nullable|date',
            'delegation_end' => 'nullable|date|after_or_equal:delegation_start',
        ]);

        $user = $request->user();
        
        $user->delegated_to_id = $validated['delegated_to_id'] ?? null;
        $user->delegation_start = $validated['delegation_start'] ?? null;
        $user->delegation_end = $validated['delegation_end'] ?? null;
        
        $user->save();

        return response()->json($user);
    }
}