<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    public function test_forgot_password_page_asks_for_a_mobile_number(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Registered Mobile Number')
            ->assertDontSee('Registered Email Address');
    }

    public function test_invalid_philippine_mobile_number_is_rejected(): void
    {
        $this->post(route('password.email'), ['phone_number' => '12345'])
            ->assertSessionHasErrors('phone_number');
    }

    public function test_otp_verification_page_requires_a_recovery_session(): void
    {
        $this->get(route('password.sms.verify'))
            ->assertRedirect(route('password.request'));
    }

    public function test_password_reset_requires_a_verified_otp_session(): void
    {
        $user = User::factory()->buyer()->create(['password' => Hash::make('old-password')]);
        $this->withSession(['password_recovery_user_id' => $user->id]);

        $this->post(route('password.sms.reset.submit'), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('password.request'));

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
