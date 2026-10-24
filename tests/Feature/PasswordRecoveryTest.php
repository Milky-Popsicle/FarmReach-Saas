<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    private const GENERIC_RESET_MESSAGE = 'If an account with that email exists, password reset instructions have been sent.';

    private const PHONE_RECOVERY_MESSAGE = 'Password recovery is currently available by email only. Phone recovery requires SMS verification, which is not currently enabled.';

    public static function accountRoles(): array
    {
        return [
            'admin' => ['admin'],
            'buyer' => ['buyer'],
            'farmer' => ['farmer'],
        ];
    }

    public static function accountRolesWithDashboardRoutes(): array
    {
        return [
            'admin' => ['admin', 'admin.dashboard'],
            'buyer' => ['buyer', 'marketplace'],
            'farmer' => ['farmer', 'farmer.marketplace'],
        ];
    }

    #[DataProvider('accountRoles')]
    public function test_password_recovery_sends_reset_notification_for_each_role(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        Notification::fake();

        $response = $this->post(route('password.email'), [
            'login_id' => $user->email,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', self::GENERIC_RESET_MESSAGE);
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_password_recovery_returns_same_message_for_existing_and_unknown_email(): void
    {
        $user = User::factory()->buyer()->create();
        Notification::fake();

        $existingResponse = $this->post(route('password.email'), [
            'login_id' => $user->email,
        ]);

        $unknownResponse = $this->post(route('password.email'), [
            'login_id' => 'missing@example.com',
        ]);

        $existingResponse->assertRedirect();
        $unknownResponse->assertRedirect();
        $existingResponse->assertSessionHas('success', self::GENERIC_RESET_MESSAGE);
        $unknownResponse->assertSessionHas('success', self::GENERIC_RESET_MESSAGE);
        Notification::assertSentTimes(ResetPassword::class, 1);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'missing@example.com']);
    }

    public function test_password_recovery_does_not_report_success_when_delivery_fails(): void
    {
        $user = User::factory()->buyer()->create();
        Log::spy();

        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => $user->email])
            ->andThrow(new TransportException('SMTP delivery failed for '.$user->email));

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'login_id' => $user->email,
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('error', 'We could not send the password reset email right now. Please try again in a few minutes.');
        $response->assertSessionMissing('success');
        $response->assertSessionHasInput('login_id', $user->email);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $user->id,
            'action' => 'Password Reset Requested',
        ]);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($user): bool {
                $loggedContext = (string) json_encode($context);

                return $message === 'Password reset email delivery failed.'
                    && $context['type'] === 'transport'
                    && $context['exception'] === TransportException::class
                    && ! str_contains($loggedContext, $user->email)
                    && ! str_contains($loggedContext, 'SMTP delivery failed');
            });
    }

    public function test_password_recovery_throttled_response_does_not_claim_a_new_email_was_sent(): void
    {
        $user = User::factory()->buyer()->create();

        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => $user->email])
            ->andReturn(Password::RESET_THROTTLED);

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'login_id' => $user->email,
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('success', 'If you do not receive the email, please wait a minute before requesting another reset link.');
        $response->assertSessionMissing('error');
    }

    public function test_phone_number_recovery_is_rejected_without_sending_a_notification(): void
    {
        $user = User::factory()->farmer()->create();
        Notification::fake();

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'login_id' => $user->phone_number,
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasErrors(['login_id' => self::PHONE_RECOVERY_MESSAGE]);
        Notification::assertNothingSent();
    }

    public function test_password_recovery_form_explains_that_phone_recovery_is_unavailable(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertSee('Phone recovery requires SMS verification, which is not currently enabled.');
    }

    public function test_reset_notification_contains_the_farmreach_reset_url(): void
    {
        $user = User::factory()->admin()->create();
        $notification = new ResetPassword('plain-token');

        $mail = $notification->toMail($user);

        $this->assertSame(
            route('password.reset', ['token' => 'plain-token', 'email' => $user->email]),
            $mail->actionUrl,
        );
    }

    public function test_reset_password_page_renders_the_token_and_email(): void
    {
        $user = User::factory()->buyer()->create([
            'email' => 'buyer@example.com',
        ]);
        $token = Password::broker()->createToken($user);

        $response = $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]));

        $response->assertOk();
        $response->assertSee('Reset Password');
        $response->assertSee('buyer@example.com');
        $response->assertSee($token);
    }

    public function test_invalid_reset_link_redirects_to_the_request_page(): void
    {
        $user = User::factory()->buyer()->create();

        $response = $this->get(route('password.reset', [
            'token' => 'invalid-token',
            'email' => $user->email,
        ]));

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasErrors([
            'email' => 'This password reset link is invalid or has expired. Please request a new one.',
        ]);
    }

    #[DataProvider('accountRolesWithDashboardRoutes')]
    public function test_user_can_reset_password_with_valid_token_for_each_role(string $role, string $dashboardRoute): void
    {
        $user = User::factory()->{$role}()->create([
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success', 'Your password has been reset. You can now sign in with your new password.');

        $updatedUser = $user->fresh();

        $this->assertTrue(Hash::check('new-password', $updatedUser->password));
        $this->assertFalse(Hash::check('old-password', $updatedUser->password));
        $this->assertSame($role, $updatedUser->role);
        $this->assertSame($user->email, $updatedUser->email);
        $this->assertSame($user->phone_number, $updatedUser->phone_number);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->post(route('login.submit'), [
            'login_id' => $user->email,
            'password' => 'old-password',
        ])->assertSessionHasErrors('login_id');

        $this->assertGuest();

        $this->post(route('login.submit'), [
            'login_id' => $user->email,
            'password' => 'new-password',
        ])->assertRedirect(route($dashboardRoute));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_reset_token_does_not_change_password(): void
    {
        $user = User::factory()->buyer()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->from(route('password.reset', [
            'token' => 'invalid-token',
            'email' => $user->email,
        ]))->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'email' => 'This password reset link is invalid or has expired. Please request a new one.',
        ]);
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_expired_reset_token_does_not_change_password(): void
    {
        $user = User::factory()->buyer()->create([
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::broker()->createToken($user);

        $this->travel(61)->minutes();

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'This password reset link is invalid or has expired. Please request a new one.',
        ]);
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));

        $this->travelBack();
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        $user = User::factory()->buyer()->create([
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'This password reset link is invalid or has expired. Please request a new one.',
        ]);
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertFalse(Hash::check('another-password', $user->fresh()->password));
    }

    public function test_new_password_confirmation_must_match(): void
    {
        $user = User::factory()->buyer()->create([
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_new_password_must_be_at_least_six_characters(): void
    {
        $user = User::factory()->buyer()->create([
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_password_recovery_requests_are_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('password.email'), [
                'login_id' => "user{$attempt}@example.com",
            ])->assertRedirect();
        }

        $this->post(route('password.email'), [
            'login_id' => 'user6@example.com',
        ])->assertTooManyRequests();
    }
}
