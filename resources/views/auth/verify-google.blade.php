<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VehiSights — Verify Email</title>
    @vite(['resources/css/pages/auth-login.css', 'resources/js/ui-enhancements.js'])
</head>
<body>
<div class="shell">
    <div class="card">
        <div class="panel-left">
            <div class="brand-mark">
                <div class="brand-icon">
                    <svg viewBox="0 0 14 14"><path d="M7 2C4.2 2 2 4.2 2 7s2.2 5 5 5 5-2.2 5-5M5 10l2 2 2-2"/></svg>
                </div>
                <span class="brand-name">VehiSights</span>
            </div>
            <div class="panel-body">
                <p class="panel-tagline">One more step.<strong>Verify securely.</strong></p>
                <p class="panel-sub">Protecting access to your traffic data.</p>
            </div>
        </div>

        <div class="panel-right">
            <img src="/images/LGUSANTOTOMAS.png" alt="LGU Santo Tomas" class="logo-top">

            <div class="form-header">
                <h1>Check your email</h1>
                <p>We sent a 6-digit code to <strong>{{ $maskedEmail }}</strong>. It expires in {{ $expirationMinutes }} minutes.</p>
            </div>

            @if (session('status'))
                <div class="auth-message auth-message-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('google.verify') }}">
                @csrf
                <div class="field">
                    <label for="code">Verification code</label>
                    <input class="verification-code" type="text" id="code" name="code" value="{{ old('code') }}" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus required>
                    @error('code') <small class="field-error">{{ $message }}</small> @enderror
                </div>

                <button type="submit" class="btn-primary">Verify and continue</button>
            </form>

            <form method="POST" action="{{ route('google.verify.resend') }}" class="resend-form">
                @csrf
                <span>Didn't receive the code?</span>
                <button type="submit">Send a new code</button>
            </form>

            <p class="form-footer"><a href="{{ route('login') }}">Back to login</a></p>
        </div>
    </div>
</div>
</body>
</html>
