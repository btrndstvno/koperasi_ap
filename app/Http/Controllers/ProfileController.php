<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Show profile settings form.
     */
    public function edit()
    {
        $user = Auth::user();
        return view('profile.settings', compact('user'));
    }

    /**
     * Update profile settings.
     * 
     * Jika user adalah member, sinkronkan email ke tabel members.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 
                'email', 
                'max:255', 
                Rule::unique('users')->ignore($user->id),
            ],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        // Update user data
        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // Update password jika diisi
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Sinkronkan email ke tabel members jika user adalah member
        if ($user->isMember() && $user->member) {
            $user->member->update(['email' => $validated['email']]);
        }

        return back()->with('success', 'Profil berhasil diperbarui.');
    }
}
