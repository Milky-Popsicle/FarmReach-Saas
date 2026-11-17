<?php

namespace Tests\Feature;

use App\Models\PasswordRecoveryCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsPasswordRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.infobip.base_url' => 'https://test.api.infobip.com',
            'services.infobip.api_key' => 'test-api-key',
            'services.infobip.sender' => 'InfoSMS',
        ]);
    }

    public function test_farmer_can_request_a_password_recovery_sms(): void
    {
        $user = User::factory()->farmer()->create(['phone_number' => '09171234567']);

        Http::fake([
            'test.api.infobip.com/sms/3/messages' => Http::response([
                'messages' => [['messageId' => 'test-message-id']],
            ], 200),
        ]);

        $this->post(route('password.email'), ['phone_number' => '09171234567'])
            ->assertRedirect(route('password.sms.verify'));

        $this->assertDatabaseHas('password_recovery_codes', ['user_id' => $user->id]);

        Http::assertSent(fn ($request) =>
            $request->hasHeader('Authorization', 'App test-api-key')
            && data_get($request->data(), 'messages.0.destinations.0.to') === '+639171234567'
        );
    }

    public function test_verified_otp_allows_password_reset_and_is_consumed(): void
    {
        $user = User::factory()->buyer()->create(['password' => Hash::make('old-password')]);

        $this->withSession([
            'password_recovery_started' => true,
            'password_recovery_user_id' => $user->id,
        ]);

        PasswordRecoveryCode::create([
            'user_id' => $user->id,
            'otp_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->post(route('password.sms.verify.submit'), ['code' => '123456'])
            ->assertRedirect(route('password.sms.reset'));

        $this->post(route('password.sms.reset.submit'), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->assertDatabaseMissing('password_recovery_codes', ['user_id' => $user->id]);
    }

    public function test_incorrect_otp_increments_attempt_counter(): void
    {
        $user = User::factory()->farmer()->create();

        $this->withSession([
            'password_recovery_started' => true,
            'password_recovery_user_id' => $user->id,
        ]);

        PasswordRecoveryCode::create([
            'user_id' => $user->id,
            'otp_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->post(route('password.sms.verify.submit'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, PasswordRecoveryCode::where('user_id', $user->id)->value('attempts'));
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = User::factory()->buyer()->create(['password' => Hash::make('old-password')]);

        $this->withSession([
            'password_recovery_started' => true,
            'password_recovery_user_id' => $user->id,
        ]);

        PasswordRecoveryCode::create([
            'user_id' => $user->id,
            'otp_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->subSecond(),
        ]);

        $this->post(route('password.sms.verify.submit'), ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_unverified_recovery_session_cannot_reset_password(): void
    {
        $user = User::factory()->buyer()->create(['password' => Hash::make('old-password')]);

        $this->withSession([
            'password_recovery_started' => true,
            'password_recovery_user_id' => $user->id,
        ]);

        PasswordRecoveryCode::create([
            'user_id' => $user->id,
            'otp_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->post(route('password.sms.reset.submit'), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('password.request'));

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_unknown_mobile_number_gets_generic_response_without_sending_sms(): void
    {
        Http::fake();

        $this->post(route('password.email'), ['phone_number' => '09179998888'])
            ->assertRedirect(route('password.sms.verify'))
            ->assertSessionHas('success');

        Http::assertNothingSent();
        $this->assertDatabaseCount('password_recovery_codes', 0);
    }

    public function test_resend_is_throttled_immediately_after_initial_code(): void
    {
        User::factory()->farmer()->create(['phone_number' => '09171234567']);

        Http::fake([
            'test.api.infobip.com/sms/3/messages' => Http::response([
                'messages' => [['messageId' => 'test-message-id']],
            ], 200),
        ]);

        $this->post(route('password.email'), ['phone_number' => '09171234567'])
            ->assertRedirect(route('password.sms.verify'));

        $this->from(route('password.sms.verify'))
            ->post(route('password.sms.resend'))
            ->assertRedirect(route('password.sms.verify'));

        Http::assertSentCount(1);
    }


    public function test_resend_sms_failure_keeps_the_existing_otp_valid(): void
    {
        $user = User::factory()->farmer()->create(['phone_number' => '09171234567']);
        $existingCode = PasswordRecoveryCode::create([
            'user_id' => $user->id,
            'otp_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withSession([
            'password_recovery_started' => true,
            'password_recovery_user_id' => $user->id,
        ]);

        Http::fake([
            'test.api.infobip.com/sms/3/messages' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $this->from(route('password.sms.verify'))
            ->post(route('password.sms.resend'))
            ->assertRedirect(route('password.sms.verify'));

        $this->assertTrue(Hash::check('123456', $existingCode->fresh()->otp_hash));
    }

    public function test_sms_delivery_failure_does_not_leave_a_valid_recovery_code(): void
    {
        $user = User::factory()->farmer()->create(['phone_number' => '09171234567']);

        Http::fake([
            'test.api.infobip.com/sms/3/messages' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $this->post(route('password.email'), ['phone_number' => '09171234567'])
            ->assertRedirect(route('password.sms.verify'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('password_recovery_codes', ['user_id' => $user->id]);
    }
}
