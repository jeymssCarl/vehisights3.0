<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users',
            'department' => 'required|string|in:City Planning',
            'role'       => 'required|in:super_admin,admin,analyst,viewer',
            'password'   => 'required|min:8|confirmed',
            'terms'      => 'accepted',
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'name'       => $validated['first_name'] . ' ' . $validated['last_name'], // Add this line
            'email'      => $validated['email'],
            'department' => 'City Planning',
            'role'       => $validated['role'],
            'password'   => Hash::make($validated['password']),
            'is_active'  => true,
        ]);

        Auth::login($user);

        return redirect('/dashboard');
    }
}