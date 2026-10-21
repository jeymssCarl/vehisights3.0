<?php

namespace App\Services;

use App\Mail\GoogleVerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GoogleVerificationService
{
    public const EXPIRATION_MINUTES = 10;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public function send(User $user, bool $enforceCooldown = false): bool
    {
        $existing = $user->verificationCode;

        if ($enforceCooldown && $existing?->last_sent_at?->isAfter(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
            return false;
        }

        $code = (string) random_int(100000, 999999);

        $verification = EmailVerificationCode::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
                'attempts' => 0,
                'last_sent_at' => now(),
            ]
        );

        try {
            Mail::to($user->email)->send(new GoogleVerificationCodeMail($code));
        } catch (Throwable $exception) {
            $verification->delete();

            throw $exception;
        }

        return true;
    }
}
