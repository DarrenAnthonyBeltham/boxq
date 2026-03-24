<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->role !== 'admin') {
            $users = User::select('_id', 'name', 'email', 'department', 'role')->get();
            return response()->json($users);
        }

        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%");
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $currentUser = $request->user();

        if ($currentUser->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only admins can create users.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:employee,manager,finance,admin,vendor',
            'department' => 'required|string|max:255',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'department' => $validated['department'],
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, $id)
    {
        $currentUser = $request->user();

        if ($currentUser->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only admins can edit users.'], 403);
        }

        $userToEdit = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id . ',_id',
            'password' => 'nullable|string|min:8',
            'role' => 'required|string|in:employee,manager,finance,admin,vendor',
            'department' => 'required|string|max:255',
        ]);

        $userToEdit->name = $validated['name'];
        $userToEdit->email = $validated['email'];
        $userToEdit->role = $validated['role'];
        $userToEdit->department = $validated['department'];

        if (!empty($validated['password'])) {
            $userToEdit->password = Hash::make($validated['password']);
        }

        $userToEdit->save();

        return response()->json($userToEdit);
    }

    public function setDelegation(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'manager') {
            return response()->json(['message' => 'Only managers can delegate approvals.'], 403);
        }

        $validated = $request->validate([
            'delegated_to_id' => 'nullable|string|exists:users,_id',
            'delegation_start' => 'nullable|date',
            'delegation_end' => 'nullable|date|after_or_equal:delegation_start',
        ]);

        $user->delegated_to_id = $validated['delegated_to_id'];
        $user->delegation_start = $validated['delegation_start'] ? Carbon::parse($validated['delegation_start'])->format('Y-m-d') : null;
        $user->delegation_end = $validated['delegation_end'] ? Carbon::parse($validated['delegation_end'])->format('Y-m-d') : null;
        $user->save();

        return response()->json(['message' => 'Delegation settings updated successfully.', 'user' => $user]);
    }
}