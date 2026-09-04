<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
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

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'login_id' => 'required|string',
        ]);

        $loginId = trim($request->login_id);
        $field = filter_var($loginId, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone_number';

        $user = User::where($field, $loginId)->first();

        if ($user) {
            ActivityLog::log($user->id, 'Password Reset Requested', "Password reset link requested for {$user->name}.");
            return back()->with('success', 'Password reset instructions have been sent to your registered contact.');
        }

        return back()->withErrors(['login_id' => 'No account found with that email or phone number.']);
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
            'email' => 'required|email|max:190|unique:users,email,' . $user->id,
            'phone_number' => 'nullable|string|max:20|unique:users,phone_number,' . $user->id,
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

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $userData['avatar_path'] = '/storage/' . $path;
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
