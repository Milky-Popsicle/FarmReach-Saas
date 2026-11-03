<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HttpsProxyTest extends TestCase
{
    /**
     * The direct peer as seen by the application behind Railway.
     *
     * @var array<string, string>
     */
    private const PROXY_SERVER = ['REMOTE_ADDR' => '127.0.0.1'];

    private const APP_HOST = 'http://farmreach.test';

    public function test_forwarded_https_generates_secure_login_registration_and_redirect_urls(): void
    {
        $this->withServerVariables(self::PROXY_SERVER)
            ->withHeader('X-Forwarded-Proto', 'https');

        $this->get(self::APP_HOST.'/login')
            ->assertOk()
            ->assertSee('action="https://farmreach.test/login"', false)
            ->assertSee('href="https://farmreach.test/register"', false)
            ->assertSee('href="https://farmreach.test/forgot-password"', false);

        $this->assertSame('https://farmreach.test/register', route('register.submit'));

        $this->get(self::APP_HOST.'/')->assertRedirect('https://farmreach.test/marketplace');
    }

    public function test_forwarded_host_cannot_change_the_generated_host(): void
    {
        $response = $this->withServerVariables(self::PROXY_SERVER)
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withHeader('X-Forwarded-Host', 'attacker.example')
            ->get(self::APP_HOST.'/login');

        $response->assertOk()
            ->assertSee('action="https://farmreach.test/login"', false)
            ->assertDontSee('attacker.example');
    }

    public function test_trusted_proxy_middleware_uses_the_direct_peer_and_ignores_forwarded_host(): void
    {
        Route::get('/_test/proxy', fn (Request $request) => response()->json([
            'secure' => $request->isSecure(),
            'host' => $request->getHost(),
            'ip' => $request->ip(),
            'trusted_proxies' => $request::getTrustedProxies(),
        ]));

        $response = $this->withServerVariables(self::PROXY_SERVER)
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-For' => '203.0.113.10',
                'X-Forwarded-Host' => 'attacker.example',
            ])
            ->get(self::APP_HOST.'/_test/proxy');

        $response->assertOk()->assertJson([
            'secure' => true,
            'host' => 'farmreach.test',
            'ip' => '203.0.113.10',
            'trusted_proxies' => ['127.0.0.1'],
        ]);
    }

    public function test_generated_asset_urls_use_https_when_the_proxy_terminates_tls(): void
    {
        $this->withServerVariables(self::PROXY_SERVER)
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get(self::APP_HOST.'/login')
            ->assertOk();

        $this->assertSame(
            'https://farmreach.test/storage/products/example.jpg',
            asset('storage/products/example.jpg'),
        );
    }

    public function test_plain_http_requests_are_not_forced_to_https(): void
    {
        $this->withServerVariables(self::PROXY_SERVER)
            ->get(self::APP_HOST.'/login')
            ->assertOk()
            ->assertSee('action="http://farmreach.test/login"', false);
    }

    public function test_session_cookie_is_secure_when_secure_cookies_are_enabled(): void
    {
        config(['session.secure' => true]);

        $response = $this->withServerVariables(self::PROXY_SERVER)
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get(self::APP_HOST.'/login');

        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie): bool => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isSecure());
    }
}
