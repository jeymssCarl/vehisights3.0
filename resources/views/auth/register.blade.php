<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VehiSights — Create Account</title>
    @vite(['resources/css/pages/auth-register.css', 'resources/js/ui-enhancements.js'])
</head>
<body>
<div class="shell">
    <div class="card">

        <!-- LEFT PANEL – Form -->
        <div class="panel-left">
            <img src="/images/LGUSANTOTOMAS.png" alt="LGU Santo Tomas" class="logo-top">

            <div class="progress-container">
                <div class="progress-header">
                    <span>Profile completion</span>
                    <span id="progressPercent">0%</span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
            </div>

            <div class="form-header">
                <div class="admin-badge">
                    <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Account Registration
                </div>
                <h1>Create account</h1>
                <p>Set up your LGU account credentials.</p>
            </div>

            <form method="POST" action="{{ route('register') }}" id="registerForm">
                @csrf

                <div class="field-row">
                    <div class="field">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" placeholder="Juan" value="{{ old('first_name') }}" autocomplete="given-name" required>
                        @error('first_name') <small class="field-error">{{ $message }}</small> @enderror
                    </div>
                    <div class="field">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" placeholder="Dela Cruz" value="{{ old('last_name') }}" autocomplete="family-name" required>
                        @error('last_name') <small class="field-error">{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="field">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="you@santotomas.gov.ph" value="{{ old('email') }}" autocomplete="email" required>
                    @error('email') <small class="field-error">{{ $message }}</small> @enderror
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" value="City Planning" readonly required>
                        @error('department') <small class="field-error">{{ $message }}</small> @enderror
                    </div>
                    <div class="field">
                        <label for="role">Initial Role</label>
                        <div class="select-wrap">
                            <select id="role" disabled>
                                <option selected>Viewer (pending approval)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="pw-wrap">
                        <input type="password" id="password" name="password" placeholder="Min. 8 characters" autocomplete="new-password" oninput="checkStrength(this.value); updateProgress()" required>
                        <button type="button" class="pw-toggle" onclick="togglePw('password','eye1')">
                            <svg id="eye1" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="strength-meter">
                        <div class="strength-track"><div class="strength-fill" id="strength-fill"></div></div>
                        <span class="strength-label" id="strength-label">Enter a password</span>
                    </div>
                    @error('password') <small class="field-error">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="pw-wrap">
                        <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Re-enter password" autocomplete="new-password" oninput="updateProgress()" required>
                        <button type="button" class="pw-toggle" onclick="togglePw('password_confirmation','eye2')">
                            <svg id="eye2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    @error('password_confirmation') <small class="field-error">{{ $message }}</small> @enderror
                </div>

                <label class="terms-row">
                    <input type="checkbox" name="terms" id="termsCheckbox" required {{ old('terms') ? 'checked' : '' }} onchange="updateProgress()">
                    <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a> of LGU Santo Tomas.</span>
                </label>
                @error('terms') <small class="field-error" style="margin-top:-12px;margin-bottom:10px;">{{ $message }}</small> @enderror

                <button type="submit" class="btn-primary">
                    Create Account
                    <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                </button>
            </form>

            <p class="form-footer">Already have an account? <a href="{{ url('/login') }}">Log in</a></p>
        </div>

        <!-- RIGHT PANEL – Maroon side -->
        <div class="panel-right">
            <div class="brand-mark">
                <span class="brand-name">VehiSights</span>
                <div class="brand-icon">
                    <svg viewBox="0 0 14 14"><path d="M7 2C4.2 2 2 4.2 2 7s2.2 5 5 5 5-2.2 5-5M5 10l2 2 2-2"/></svg>
                </div>
            </div>
            <ul class="steps">
                <li class="step"><div class="step-num">1</div><div class="step-text"><strong>Create your account</strong><span>Fill in your official LGU credentials and department.</span></div></li>
                <li class="step"><div class="step-num">2</div><div class="step-text"><strong>Await verification</strong><span>A super admin will approve your access within 24 hours.</span></div></li>
                <li class="step"><div class="step-num">3</div><div class="step-text"><strong>Access the dashboard</strong><span>Monitor live vehicle counts and generate traffic reports.</span></div></li>
            </ul>
            <div class="panel-bottom">
                <p class="panel-tagline">Count Smarter.<strong>Decide Faster.</strong></p>
                <p class="panel-sub">Real-time vehicle data for roads that never stop moving.</p>
                <div class="live-badge"><span class="live-dot"></span> Live traffic data</div>
            </div>
        </div>

    </div>
</div>

@vite('resources/js/pages/auth-register.js')
</body>
</html>
