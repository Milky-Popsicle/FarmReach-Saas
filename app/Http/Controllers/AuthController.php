<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    private const GENERIC_RESET_MESSAGE = 'If an account with that email exists, password reset instructions have been sent.';

    private const DELIVERY_FAILED_MESSAGE = 'We could not send the password reset email right now. Please try again in a few minutes.';

    private const RESET_THROTTLED_MESSAGE = 'If you do not receive the email, please wait a minute before requesting another reset link.';

    private const PHONE_RECOVERY_MESSAGE = 'Password recovery is currently available by email only. Phone recovery requires SMS verification, which is not currently enabled.';

    private const INVALID_RESET_MESSAGE = 'This password reset link is invalid or has expired. Please request a new one.';

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

        // Check if an admin already exists (spec requirement: Admin can only be created once)
        $adminExists = User::where('role', 'admin')->exists();

        return view('auth.register', compact('adminExists'));
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:190',
            'email' => 'required|string|email|max:190|unique:users',
            'phone_number' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:buyer,farmer,admin',
            'address' => 'nullable|string|max:500',
        ]);

        // Enforce rule: Admin can only be created once
        if ($validated['role'] === 'admin') {
            if (User::where('role', 'admin')->exists()) {
                return back()->withErrors(['role' => 'An Administrator account already exists. Only one Admin is permitted.'])->withInput();
            }
        }

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

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login_id' => ['required', 'string', 'email', 'max:190'],
        ], [
            'login_id.required' => 'Enter your registered email address.',
            'login_id.email' => self::PHONE_RECOVERY_MESSAGE,
        ]);

        $email = $validated['login_id'];

        try {
            $status = Password::sendResetLink(['email' => $email]);
        } catch (TransportExceptionInterface $exception) {
            $this->logPasswordResetFailure($exception, 'transport');

            return back()
                ->with('error', self::DELIVERY_FAILED_MESSAGE)
                ->withInput($request->only('login_id'));
        } catch (\Throwable $exception) {
            $this->logPasswordResetFailure($exception, 'unexpected');

            return back()
                ->with('error', self::DELIVERY_FAILED_MESSAGE)
                ->withInput($request->only('login_id'));
        }

        if ($status === Password::RESET_LINK_SENT) {
            $user = User::where('email', $email)->first();

            if ($user) {
                ActivityLog::log($user->id, 'Password Reset Requested', "Password reset link requested for {$user->name}.");
            }
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->with('success', self::RESET_THROTTLED_MESSAGE);
        }

        return back()->with('success', self::GENERIC_RESET_MESSAGE);
    }

    private function logPasswordResetFailure(\Throwable $exception, string $type): void
    {
        Log::error('Password reset email delivery failed.', [
            'type' => $type,
            'exception' => $exception::class,
            'mailer' => (string) config('mail.default'),
            'code' => $exception->getCode(),
        ]);
    }

    public function showResetForm(Request $request, string $token): View|RedirectResponse
    {
        $email = $request->string('email')->toString();
        $user = User::where('email', $email)->first();

        if (! $user || ! Password::broker()->tokenExists($user, $token)) {
            return redirect()->route('password.request')->withErrors([
                'email' => self::INVALID_RESET_MESSAGE,
            ]);
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'email.required' => 'Enter the email address associated with the reset link.',
            'password.required' => 'Enter a new password.',
            'password.min' => 'The new password must be at least 6 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                ActivityLog::log($user->id, 'Password Reset', 'Password was reset using the recovery link.');

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Your password has been reset. You can now sign in with your new password.');
        }

        return back()->withErrors([
            'email' => self::INVALID_RESET_MESSAGE,
        ])->withInput($request->only('email'));
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
