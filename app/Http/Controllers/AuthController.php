<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PasswordRecoveryCode;
use App\Models\User;
use App\Services\InfobipSmsService;
use App\Support\PhilippineMobileNumber;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const GENERIC_RESET_MESSAGE = 'If an account matches that mobile number, a verification code has been sent.';

    private const DELIVERY_FAILED_MESSAGE = 'We could not send a verification code right now. Please try again in a few minutes.';

    private const RESET_THROTTLED_MESSAGE = 'Please wait 60 seconds before requesting another verification code.';

    private const PHONE_RECOVERY_MESSAGE = 'Enter a valid Philippine mobile number in 09XXXXXXXXX format.';

    private const INVALID_RESET_MESSAGE = 'This verification code is invalid or has expired. Please request a new one.';

    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login_id' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginId = trim($credentials['login_id']);
        $password = $credentials['password'];

        // Determine if login_id is email or phone number
        $field = filter_var($loginId, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone_number';

        if (Auth::attempt([$field => $loginId, 'password' => $password], $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            ActivityLog::log($user->id, 'User Login', "User {$user->name} ({$user->role}) logged in.");

            return $this->redirectBasedOnRole($user);
        }

        return back()->withErrors([
            'login_id' => 'The provided credentials do not match our records.',
        ])->withInput($request->only('login_id', 'remember'));
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:190',
            'email' => 'required|string|email|max:190|unique:users',
            'phone_number' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:buyer,farmer',
            'address' => 'nullable|string|max:500',
        ], [
            'role.in' => 'Administrator accounts cannot be created through public registration.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'address' => $validated['address'] ?? null,
        ]);

        ActivityLog::log($user->id, 'User Registered', "New {$user->role} account created for {$user->name}.");

        Auth::login($user);

        return $this->redirectBasedOnRole($user)->with('success', 'Account created successfully! Welcome to FarmReach Cloud.');
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            ActivityLog::log(Auth::id(), 'User Logout', 'User logged out.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'You have been logged out.');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request, InfobipSmsService $sms): RedirectResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:30'],
        ], [
            'phone_number.required' => 'Enter your registered mobile number.',
        ]);

        $phone = PhilippineMobileNumber::normalize($validated['phone_number']);

        if ($phone === null) {
            return back()->withErrors(['phone_number' => self::PHONE_RECOVERY_MESSAGE])
                ->withInput($request->only('phone_number'));
        }

        $key = 'password-recovery-request:'.hash('sha256', $phone.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('success', self::GENERIC_RESET_MESSAGE);
        }

        RateLimiter::hit($key, 60);

                $phoneVariants = PhilippineMobileNumber::variants($phone);
        $digitsOnlyPhone = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone_number, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '')";
        $placeholders = implode(',', array_fill(0, count($phoneVariants), '?'));

        // Existing registrations may contain spaces, dashes, parentheses, or periods.
        // Compare their punctuation-free digits as well as common canonical formats.
        $user = User::query()
            ->whereIn('phone_number', $phoneVariants)
            ->orWhereRaw("{$digitsOnlyPhone} IN ({$placeholders})", $phoneVariants)
            ->first();
        $request->session()->regenerate();

        // Use the same response and next screen for known and unknown numbers
        // so the recovery form does not disclose whether an account exists.
        $request->session()->put('password_recovery_started', true);
        $request->session()->put('password_recovery_phone_masked', PhilippineMobileNumber::mask($phone));
        $request->session()->forget(['password_recovery_user_id', 'password_recovery_verified_at']);

        if (! $user) {
            return redirect()->route('password.sms.verify')->with('success', self::GENERIC_RESET_MESSAGE);
        }

        $code = (string) random_int(100000, 999999);

        try {
            $sms->send($phone, "Your FarmReach Cloud password reset code is {$code}. It expires in 5 minutes. Do not share this code.");
        } catch (\Throwable $exception) {
            Log::warning('Password recovery SMS could not be submitted.', [
                'exception' => $exception::class,
            ]);

            // Keep the same next screen and response for known and unknown numbers.
            return redirect()->route('password.sms.verify')->with('success', self::GENERIC_RESET_MESSAGE);
        }

        PasswordRecoveryCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'otp_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5),
                'verified_at' => null,
            ]
        );

        RateLimiter::hit('password-recovery-resend:'.$user->id.'|'.$request->ip(), 60);

        $request->session()->put('password_recovery_user_id', $user->id);

        ActivityLog::log($user->id, 'Password Reset OTP Requested', 'A password recovery code was requested.');

        return redirect()->route('password.sms.verify')->with('success', self::GENERIC_RESET_MESSAGE);
    }

    public function showVerifyPasswordOtp(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('password_recovery_started') !== true) {
            return redirect()->route('password.request')->with('error', self::INVALID_RESET_MESSAGE);
        }

        return view('auth.verify-password-otp', [
            'maskedPhone' => $request->session()->get('password_recovery_phone_masked', 'your registered mobile number'),
        ]);
    }

    public function verifyPasswordOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if ($request->session()->get('password_recovery_started') !== true) {
            return redirect()->route('password.request')->with('error', self::INVALID_RESET_MESSAGE);
        }

        $userId = $request->session()->get('password_recovery_user_id');
        $recovery = $userId ? PasswordRecoveryCode::where('user_id', $userId)->first() : null;

        if (! $recovery || $recovery->expires_at->isPast() || $recovery->verified_at !== null) {
            return back()->withErrors(['code' => self::INVALID_RESET_MESSAGE]);
        }

        if ($recovery->attempts >= 5) {
            $recovery->delete();
            $request->session()->forget(['password_recovery_started', 'password_recovery_user_id', 'password_recovery_verified_at']);

            return redirect()->route('password.request')->withErrors(['code' => 'Too many incorrect attempts. Request a new code.']);
        }

        if (! Hash::check($validated['code'], $recovery->otp_hash)) {
            $recovery->increment('attempts');

            return back()->withErrors(['code' => 'The verification code is incorrect or has expired.']);
        }

        $recovery->update(['verified_at' => now()]);
        $request->session()->put('password_recovery_verified_at', now()->timestamp);

        return redirect()->route('password.sms.reset');
    }

    public function resendPasswordOtp(Request $request, InfobipSmsService $sms): RedirectResponse
    {
        $userId = $request->session()->get('password_recovery_user_id');
        $user = $userId ? User::find($userId) : null;

        // Unknown mobile numbers follow the same generic response and never
        // trigger a second SMS request.
        if ($request->session()->get('password_recovery_started') !== true) {
            return redirect()->route('password.request')->with('error', self::INVALID_RESET_MESSAGE);
        }

        if (! $user || ! $user->phone_number) {
            return back()->with('success', self::GENERIC_RESET_MESSAGE);
        }

        $key = 'password-recovery-resend:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->with('success', self::GENERIC_RESET_MESSAGE);
        }

        RateLimiter::hit($key, 60);
        $code = (string) random_int(100000, 999999);
        $phone = PhilippineMobileNumber::normalize($user->phone_number);

        if ($phone === null) {
            return redirect()->route('password.request')->with('error', self::PHONE_RECOVERY_MESSAGE);
        }

        try {
            $sms->send($phone, "Your new FarmReach Cloud password reset code is {$code}. It expires in 5 minutes. Do not share this code.");
        } catch (\Throwable $exception) {
            Log::warning('Password recovery SMS resend could not be submitted.', [
                'exception' => $exception::class,
            ]);

            return back()->with('success', self::GENERIC_RESET_MESSAGE);
        }

        PasswordRecoveryCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'otp_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5),
                'verified_at' => null,
            ]
        );

        $request->session()->forget('password_recovery_verified_at');

        return back()->with('success', self::GENERIC_RESET_MESSAGE);
    }

    public function showSmsResetForm(Request $request): View|RedirectResponse
    {
        if (! $this->hasVerifiedRecoverySession($request)) {
            return redirect()->route('password.request')->with('error', self::INVALID_RESET_MESSAGE);
        }

        return view('auth.reset-password-sms');
    }

    public function resetPasswordWithSms(Request $request): RedirectResponse
    {
        if (! $this->hasVerifiedRecoverySession($request)) {
            $request->session()->forget(['password_recovery_started', 'password_recovery_user_id', 'password_recovery_verified_at']);

            return redirect()->route('password.request')->with('error', self::INVALID_RESET_MESSAGE);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $userId = $request->session()->get('password_recovery_user_id');
        $user = User::findOrFail($userId);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        PasswordRecoveryCode::where('user_id', $user->id)->delete();
        ActivityLog::log($user->id, 'Password Reset', 'Password was reset after SMS OTP verification.');

        event(new PasswordReset($user));

        $request->session()->forget(['password_recovery_started', 'password_recovery_phone_masked', 'password_recovery_user_id', 'password_recovery_verified_at']);
        $request->session()->regenerate();

        return redirect()->route('login')->with('success', 'Your password has been reset. You can now sign in with your new password.');
    }

    private function hasVerifiedRecoverySession(Request $request): bool
    {
        $userId = $request->session()->get('password_recovery_user_id');
        $verifiedAt = $request->session()->get('password_recovery_verified_at');
        $recovery = $userId ? PasswordRecoveryCode::where('user_id', $userId)->first() : null;

        return $request->session()->get('password_recovery_started') === true
            && $recovery !== null
            && $recovery->verified_at !== null
            && $recovery->expires_at->isFuture()
            && is_numeric($verifiedAt)
            && (int) $verifiedAt >= now()->subMinutes(5)->timestamp;
    }

    public function editProfile()
    {
        $user = Auth::user();

        return view('profile.edit', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:190',
            'email' => 'required|email|max:190|unique:users,email,'.$user->id,
            'phone_number' => 'nullable|string|max:20|unique:users,phone_number,'.$user->id,
            'gender' => 'nullable|string|max:50',
            'birthday' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'bio' => 'nullable|string|max:1000',
            'password' => 'nullable|string|min:6|confirmed',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? $user->phone_number,
            'gender' => $validated['gender'] ?? null,
            'birthday' => $validated['birthday'] ?? null,
            'address' => $validated['address'] ?? null,
            'bio' => $validated['bio'] ?? null,
        ];

        if (! empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $userData['avatar_path'] = '/storage/'.$path;
        }

        $user->update($userData);

        ActivityLog::log($user->id, 'Profile Updated', "{$user->name} updated their profile details.");

        return back()->with('success', 'Profile updated successfully.');
    }

    public function redirectBasedOnRole($user)
    {
        return match ($user->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'farmer' => redirect()->route('farmer.marketplace'),
            default => redirect()->route('marketplace'),
        };
    }
}
