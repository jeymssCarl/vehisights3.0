<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'department' => 'required|string|in:City Planning',
            'password' => 'required|min:8|confirmed',
            'terms' => 'accepted',
        ]);

        User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => $validated['first_name'].' '.$validated['last_name'], // Add this line
            'email' => $validated['email'],
            'department' => 'City Planning',
            'role' => 'viewer',
            'password' => Hash::make($validated['password']),
            'is_active' => false,
        ]);

        return to_route('login')->with('status', 'Account created. Please wait for administrator approval before signing in.');
    }
}
