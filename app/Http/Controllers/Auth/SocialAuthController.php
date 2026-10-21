<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! $this->googleIsConfigured()) {
            return to_route('login')->withErrors([
                'oauth' => 'Google sign-in is not configured yet. Add the Google Client ID and Client Secret to .env.',
            ]);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->googleIsConfigured()) {
            return to_route('login')->withErrors([
                'oauth' => 'Google sign-in is not configured yet.',
            ]);
        }

        if ($request->filled('error')) {
            return to_route('login')->withErrors([
                'oauth' => 'Sign-in was cancelled or denied. Please try again.',
            ]);
        }

        try {
            $providerUser = Socialite::driver('google')->user();

            $providerId = (string) $providerUser->getId();
            $email = Str::lower(trim((string) $providerUser->getEmail()));

            if ($providerId === '' || $email === '') {
                return to_route('login')->withErrors([
                    'oauth' => 'Google did not provide a usable email address.',
                ]);
            }

            $user = DB::transaction(function () use ($providerId, $email, $providerUser) {
                $account = SocialAccount::query()
                    ->where('provider', 'google')
                    ->where('provider_user_id', $providerId)
                    ->first();

                if ($account) {
                    $user = $account->user;
                } else {
                    $user = User::query()
                        ->whereRaw('LOWER(email) = ?', [$email])
                        ->first();

                    if (! $user) {
                        [$firstName, $lastName] = $this->splitName($providerUser->getName(), $email);

                        $user = User::create([
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'name' => trim($firstName.' '.$lastName),
                            'email' => $email,
                            'email_verified_at' => now(),
                            'password' => null,
                            'department' => 'City Planning',
                            'role' => 'viewer',
                            'is_active' => true,
                        ]);
                    }

                    $user->socialAccounts()->create([
                        'provider' => 'google',
                        'provider_user_id' => $providerId,
                    ]);
                }

                // A successful Google OAuth response verifies ownership of the
                // email, so Google accounts can use the application immediately.
                $user->forceFill([
                    'email_verified_at' => $user->email_verified_at ?: now(),
                    'is_active' => true,
                ])->save();

                return $user;
            });

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        } catch (Throwable $exception) {
            Log::warning('Social sign-in failed.', [
                'provider' => 'google',
                'exception' => $exception,
            ]);

            return to_route('login')->withErrors([
                'oauth' => 'We could not complete Google sign-in. Please try again.',
            ]);
        }
    }

    private function googleIsConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    /** @return array{0: string, 1: string} */
    private function splitName(?string $name, string $email): array
    {
        $name = trim((string) $name);

        if ($name === '') {
            $name = Str::of(Str::before($email, '@'))->replace(['.', '_', '-'], ' ')->title()->toString();
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [];

        return [$parts[0] ?? 'User', $parts[1] ?? ''];
    }
}
