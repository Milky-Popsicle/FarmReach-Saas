<?php

namespace Tests\Feature;

use App\Mail\GmailOAuthTokenProvider;
use App\Mail\Transport\GmailOAuthTransport;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class GmailOAuthMailTest extends TestCase
{
    public function test_gmail_oauth_mailer_is_registered(): void
    {
        $mailer = Mail::mailer('gmail-oauth');

        $this->assertInstanceOf(
            GmailOAuthTransport::class,
            $mailer->getSymfonyTransport()
        );
    }

    public function test_transport_refreshes_access_token_and_sends_mime_message(): void
    {
        Http::fake($this->successfulGmailResponses());

        $email = $this->makeEmail();
        $sent = $this->makeTransport()->send($email, Envelope::create($email));

        $this->assertNotNull($sent);
        $this->assertSame('gmail-message-id', $sent->getMessageId());

        Http::assertSent(function (ClientRequest $request): bool {
            return str_contains($request->url(), 'oauth2.googleapis.com/token')
                && $request['grant_type'] === 'refresh_token'
                && $request['client_id'] === 'test-client-id'
                && $request['client_secret'] === 'test-client-secret'
                && $request['refresh_token'] === 'test-refresh-token';
        });

        Http::assertSent(function (ClientRequest $request): bool {
            if (! str_contains($request->url(), 'messages/send')) {
                return false;
            }

            $decoded = $this->decodeRawMessage($request);

            return $request->hasHeader('Authorization', 'Bearer test-access-token')
                && is_string($decoded)
                && str_contains($decoded, 'recipient@example.com')
                && str_contains($decoded, 'farmreach.sender@gmail.com')
                && str_contains($decoded, 'FarmReach password reset');
        });
    }

    public function test_transport_accepts_a_null_envelope_and_derives_it_from_the_message(): void
    {
        Http::fake($this->successfulGmailResponses());

        $email = $this->makeEmail();
        $sent = $this->makeTransport()->send($email);

        $this->assertNotNull($sent);
        $this->assertSame('gmail-message-id', $sent->getMessageId());

        Http::assertSent(function (ClientRequest $request): bool {
            if (! str_contains($request->url(), 'messages/send')) {
                return false;
            }

            $decoded = $this->decodeRawMessage($request);

            return is_string($decoded)
                && str_contains($decoded, 'To: recipient@example.com')
                && str_contains($decoded, 'farmreach.sender@gmail.com');
        });
    }

    public function test_gmail_payload_includes_cc_and_bcc_envelope_recipients(): void
    {
        Http::fake($this->successfulGmailResponses());

        $email = (new Email)
            ->from('farmreach.sender@gmail.com')
            ->to('recipient@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('FarmReach password reset')
            ->text('Use this link to reset your password.');

        $sent = $this->makeTransport()->send($email, Envelope::create($email));

        $this->assertNotNull($sent);

        Http::assertSent(function (ClientRequest $request): bool {
            if (! str_contains($request->url(), 'messages/send')) {
                return false;
            }

            $decoded = $this->decodeRawMessage($request);

            return is_string($decoded)
                && str_contains($decoded, 'To: recipient@example.com')
                && str_contains($decoded, 'Cc: cc@example.com')
                && str_contains($decoded, 'Bcc: bcc@example.com');
        });
    }

    public function test_gmail_payload_includes_envelope_recipients_absent_from_headers(): void
    {
        Http::fake($this->successfulGmailResponses());

        $email = $this->makeEmail();
        $envelope = new Envelope(
            new Address('farmreach.sender@gmail.com'),
            [new Address('recipient@example.com'), new Address('extra@example.com')],
        );

        $sent = $this->makeTransport()->send($email, $envelope);

        $this->assertNotNull($sent);

        Http::assertSent(function (ClientRequest $request): bool {
            if (! str_contains($request->url(), 'messages/send')) {
                return false;
            }

            $decoded = $this->decodeRawMessage($request);

            return is_string($decoded)
                && str_contains($decoded, 'To: recipient@example.com')
                && str_contains($decoded, 'Bcc: extra@example.com');
        });
    }

    public function test_access_token_is_reused_until_it_expires(): void
    {
        Http::fake($this->successfulGmailResponses());

        $transport = $this->makeTransport();

        foreach ([1, 2] as $attempt) {
            $email = $this->makeEmail();
            $transport->send($email, Envelope::create($email));
        }

        Http::assertSentCount(3);

        $tokenRequests = Http::recorded()
            ->filter(fn (array $pair): bool => str_contains(
                $pair[0]->url(),
                'oauth2.googleapis.com/token'
            ))
            ->count();

        $this->assertSame(1, $tokenRequests);
    }

    public function test_access_token_is_encrypted_in_the_cache(): void
    {
        Http::fake($this->successfulGmailResponses());

        $email = $this->makeEmail();
        $this->makeTransport()->send($email, Envelope::create($email));

        $cachedValues = Cache::store()->getStore()->all();

        $this->assertNotEmpty($cachedValues);
        $this->assertNotContains('test-access-token', array_values($cachedValues));
    }

    public function test_access_token_is_refreshed_after_cache_expiry(): void
    {
        Http::fake([
            '*oauth2.googleapis.com/token*' => Http::sequence()
                ->push(['access_token' => 'first-access-token', 'expires_in' => 3600])
                ->push(['access_token' => 'second-access-token', 'expires_in' => 3600]),
            '*gmail.googleapis.com/*' => Http::response(['id' => 'expiry-message-id'], 200),
        ]);

        $transport = $this->makeTransport();

        $firstEmail = $this->makeEmail();
        $transport->send($firstEmail, Envelope::create($firstEmail));

        $this->travel(3601)->seconds();

        $secondEmail = $this->makeEmail();
        $transport->send($secondEmail, Envelope::create($secondEmail));

        $tokenRequests = Http::recorded()
            ->filter(fn (array $pair): bool => str_contains(
                $pair[0]->url(),
                'oauth2.googleapis.com/token'
            ))
            ->count();

        $this->assertSame(2, $tokenRequests);

        Http::assertSent(fn (ClientRequest $request): bool => str_contains(
            $request->url(),
            'messages/send'
        ) && $request->hasHeader('Authorization', 'Bearer second-access-token'));

        $this->travelBack();
    }

    public function test_transport_refreshes_and_retries_once_after_unauthorized_response(): void
    {
        Http::fake([
            '*oauth2.googleapis.com/token*' => Http::sequence()
                ->push(['access_token' => 'expired-access-token', 'expires_in' => 3600])
                ->push(['access_token' => 'fresh-access-token', 'expires_in' => 3600]),
            '*gmail.googleapis.com/*' => Http::sequence()
                ->push(['error' => 'invalid_token'], 401)
                ->push(['id' => 'retried-message-id'], 200),
        ]);

        $email = $this->makeEmail();
        $sent = $this->makeTransport()->send($email, Envelope::create($email));

        $this->assertNotNull($sent);
        $this->assertSame('retried-message-id', $sent->getMessageId());

        $tokenRequests = Http::recorded()
            ->filter(fn (array $pair): bool => str_contains(
                $pair[0]->url(),
                'oauth2.googleapis.com/token'
            ))
            ->count();

        $sendRequests = Http::recorded()
            ->filter(fn (array $pair): bool => str_contains(
                $pair[0]->url(),
                'messages/send'
            ))
            ->count();

        $this->assertSame(2, $tokenRequests);
        $this->assertSame(2, $sendRequests);

        Http::assertSent(fn (ClientRequest $request): bool => str_contains(
            $request->url(),
            'messages/send'
        ) && $request->hasHeader('Authorization', 'Bearer fresh-access-token'));
    }

    public function test_transport_throws_when_credentials_are_missing(): void
    {
        Http::fake();

        $email = $this->makeEmail();

        try {
            $this->makeTransport('', '', '')->send($email, Envelope::create($email));
            $this->fail('Expected a TransportException for missing credentials.');
        } catch (TransportException $exception) {
            $this->assertStringContainsString(
                'Gmail OAuth credentials are not configured',
                $exception->getMessage()
            );
        }

        Http::assertNothingSent();
    }

    public function test_transport_throws_when_refresh_token_is_rejected(): void
    {
        Http::fake([
            '*oauth2.googleapis.com/token*' => Http::response(
                ['error' => 'invalid_grant'],
                400
            ),
        ]);

        $email = $this->makeEmail();

        try {
            $this->makeTransport()->send($email, Envelope::create($email));
            $this->fail('Expected a TransportException for a rejected refresh token.');
        } catch (TransportException $exception) {
            $this->assertStringContainsString('HTTP 400', $exception->getMessage());
            $this->assertStringContainsString('invalid_grant', $exception->getMessage());
        }

        Http::assertSentCount(1);
        Http::assertNotSent(fn (ClientRequest $request): bool => str_contains(
            $request->url(),
            'messages/send'
        ));
    }

    public function test_token_refresh_failure_does_not_echo_untrusted_response_text(): void
    {
        Http::fake([
            '*oauth2.googleapis.com/token*' => Http::response(
                ['error' => 'test-access-token'],
                400
            ),
        ]);

        $email = $this->makeEmail();

        try {
            $this->makeTransport()->send($email, Envelope::create($email));
            $this->fail('Expected a TransportException for a rejected refresh token.');
        } catch (TransportException $exception) {
            $this->assertStringContainsString('HTTP 400', $exception->getMessage());
            $this->assertStringNotContainsString('test-access-token', $exception->getMessage());
        }
    }

    public function test_token_refresh_network_failure_does_not_expose_credentials(): void
    {
        Http::fake(fn () => throw new ConnectionException(
            'client_secret=test-client-secret refresh_token=test-refresh-token'
        ));

        $email = $this->makeEmail();

        try {
            $this->makeTransport()->send($email, Envelope::create($email));
            $this->fail('Expected a TransportException for a network failure.');
        } catch (TransportException $exception) {
            $this->assertStringNotContainsString('test-client-secret', $exception->getMessage());
            $this->assertStringNotContainsString('test-refresh-token', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }

        Http::assertNotSent(fn (ClientRequest $request): bool => str_contains(
            $request->url(),
            'messages/send'
        ));
    }

    public function test_transport_throws_when_gmail_api_send_fails(): void
    {
        Http::fake([
            '*oauth2.googleapis.com/token*' => Http::response([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
            ]),
            '*gmail.googleapis.com/*' => Http::response(
                ['error' => 'backendError'],
                500
            ),
        ]);

        $email = $this->makeEmail();

        try {
            $this->makeTransport()->send($email, Envelope::create($email));
            $this->fail('Expected a TransportException for a failed Gmail API send.');
        } catch (TransportException $exception) {
            $this->assertStringContainsString('HTTP 500', $exception->getMessage());
        }

        $sendRequests = Http::recorded()
            ->filter(fn (array $pair): bool => str_contains(
                $pair[0]->url(),
                'messages/send'
            ))
            ->count();

        $this->assertSame(1, $sendRequests);
    }

    public function test_password_reset_email_is_sent_through_gmail_oauth_with_production_app_url(): void
    {
        $user = User::factory()->buyer()->create([
            'email' => 'reset-recipient@example.com',
        ]);

        config([
            'app.url' => 'https://farmreach.example',
            'mail.default' => 'gmail-oauth',
            'mail.from.address' => 'farmreach.sender@gmail.com',
            'mail.from.name' => 'FarmReach',
            'services.gmail.client_id' => 'test-client-id',
            'services.gmail.client_secret' => 'test-client-secret',
            'services.gmail.refresh_token' => 'test-refresh-token',
        ]);

        URL::forceRootUrl('https://farmreach.example');
        URL::forceScheme('https');

        Http::fake($this->successfulGmailResponses());

        $status = Password::sendResetLink(['email' => $user->email]);

        $this->assertSame(Password::RESET_LINK_SENT, $status);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        Http::assertSent(function (ClientRequest $request) use ($user): bool {
            if (! str_contains($request->url(), 'messages/send')) {
                return false;
            }

            $decoded = $this->decodeRawMessage($request);

            if (! is_string($decoded)) {
                return false;
            }

            $body = quoted_printable_decode($decoded);
            $decodedUrl = html_entity_decode($body);

            return str_contains($body, $user->email)
                && str_contains($decodedUrl, 'https://farmreach.example/reset-password/')
                && str_contains($body, 'farmreach.sender@gmail.com');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function successfulGmailResponses(): array
    {
        return [
            '*oauth2.googleapis.com/token*' => Http::response([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            '*gmail.googleapis.com/*' => Http::response([
                'id' => 'gmail-message-id',
                'threadId' => 'gmail-thread-id',
                'labelIds' => ['SENT'],
            ]),
        ];
    }

    private function makeTransport(
        string $clientId = 'test-client-id',
        string $clientSecret = 'test-client-secret',
        string $refreshToken = 'test-refresh-token',
    ): GmailOAuthTransport {
        return new GmailOAuthTransport(
            http: app(Factory::class),
            tokens: new GmailOAuthTokenProvider(
                http: app(Factory::class),
                cache: app(CacheRepository::class),
                encrypter: app(Encrypter::class),
                clientId: $clientId,
                clientSecret: $clientSecret,
                refreshToken: $refreshToken,
            ),
        );
    }

    private function makeEmail(): Email
    {
        return (new Email)
            ->from('farmreach.sender@gmail.com')
            ->to('recipient@example.com')
            ->subject('FarmReach password reset')
            ->text('Use this link to reset your password.');
    }

    private function decodeRawMessage(ClientRequest $request): ?string
    {
        $raw = $request['raw'];

        if (! is_string($raw)) {
            return null;
        }

        $decoded = base64_decode(strtr($raw, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }
}
