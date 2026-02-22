<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'errors' => ['current_password' => ['The provided current password does not match our records.']]
            ], 422);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return response()->json(['message' => 'Password updated successfully']);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                $oldPath = str_replace('/storage/', 'public/', $user->avatar);
                Storage::delete($oldPath);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            
            $user->avatar = '/storage/' . $path;
            $user->save();

            return response()->json([
                'message' => 'Avatar updated successfully',
                'user' => $user
            ]);
        }

        return response()->json(['message' => 'No image uploaded'], 400);
    }

    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.email_on_status' => 'required|boolean',
            'preferences.email_on_new' => 'required|boolean',
        ]);

        $user = $request->user();
        $user->preferences = $validated['preferences'];
        $user->save();

        return response()->json([
            'message' => 'Preferences updated successfully',
            'user' => $user
        ]);
    }
}