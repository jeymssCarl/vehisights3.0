<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Throwable;

class GoogleVerificationController extends Controller
{
    private const SESSION_KEY = 'google_verification_user_id';

    private const MAX_ATTEMPTS = 5;

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return to_route('login')->withErrors([
                'oauth' => 'Please sign in with Google before entering a verification code.',
            ]);
        }

        if ($user->google_verified_at) {
            return $this->completeLogin($request, $user);
        }

        return view('auth.verify-google', [
            'maskedEmail' => $this->maskEmail($user->email),
            'expirationMinutes' => GoogleVerificationService::EXPIRATION_MINUTES,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'regex:/^\d{6}$/'],
        ], [
            'code.regex' => 'Enter the 6-digit code sent to your email.',
        ]);

        $user = $this->pendingUser($request);

        if (! $user) {
            return to_route('login')->withErrors([
                'oauth' => 'Your verification session has ended. Please sign in with Google again.',
            ]);
        }

        $verification = $user->verificationCode;

        if (! $verification || $verification->expires_at->isPast()) {
            $verification?->delete();

            return back()->withErrors([
                'code' => 'This code has expired. Request a new code below.',
            ]);
        }

        if ($verification->attempts >= self::MAX_ATTEMPTS) {
            return back()->withErrors([
                'code' => 'Too many incorrect attempts. Request a new code below.',
            ]);
        }

        if (! Hash::check($validated['code'], $verification->code_hash)) {
            $verification->increment('attempts');

            return back()->withErrors([
                'code' => 'The verification code is incorrect.',
            ]);
        }

        DB::transaction(function () use ($user, $verification) {
            $user->forceFill(['google_verified_at' => now()])->save();
            $verification->delete();
        });

        return $this->completeLogin($request, $user);
    }

    public function resend(Request $request, GoogleVerificationService $verificationService): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return to_route('login')->withErrors([
                'oauth' => 'Your verification session has ended. Please sign in with Google again.',
            ]);
        }

        try {
            if (! $verificationService->send($user, true)) {
                return back()->withErrors([
                    'code' => 'Please wait one minute before requesting another code.',
                ]);
            }
        } catch (Throwable) {
            return back()->withErrors([
                'code' => 'We could not send a new code. Please try again shortly.',
            ]);
        }

        return back()->with('status', 'A new verification code was sent.');
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get(self::SESSION_KEY);

        if (! $userId) {
            return null;
        }

        return User::query()
            ->whereKey($userId)
            ->whereHas('socialAccounts', fn ($query) => $query->where('provider', 'google'))
            ->first();
    }

    private function completeLogin(Request $request, User $user): RedirectResponse
    {
        Auth::login($user, true);
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('*', max(3, mb_strlen($local) - mb_strlen($visible))).'@'.$domain;
    }
}
