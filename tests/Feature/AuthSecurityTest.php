<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    public function test_public_registration_rejects_unsupported_roles(): void
    {
        $response = $this->post(route('register.submit'), [
            'name' => 'Unsupported Role',
            'email' => 'unsupported-role@example.test',
            'phone_number' => '09170004444',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'supplier',
        ]);

        $response->assertSessionHasErrors(['role']);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unsupported-role@example.test']);
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $user = User::factory()->buyer()->create([
            'email' => 'throttled-login@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.submit'), [
                'login_id' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('login_id');
        }

        $this->post(route('login.submit'), [
            'login_id' => $user->email,
            'password' => 'correct-password',
        ])->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_registration_attempts_are_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('register.submit'), [
                'email' => "throttled-register-{$attempt}@example.test",
                'phone_number' => '09170005555',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'buyer',
            ])->assertSessionHasErrors('name');
        }

        $this->post(route('register.submit'), [
            'name' => 'Throttled Registration',
            'email' => 'throttled-register-6@example.test',
            'phone_number' => '09170006666',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'buyer',
        ])->assertTooManyRequests();

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }
}
