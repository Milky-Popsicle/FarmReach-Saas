<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    /**
     * The direct peer as seen by the application behind Railway.
     *
     * @var array<string, string>
     */
    private const PROXY_SERVER = ['REMOTE_ADDR' => '127.0.0.1'];

    private const APP_HOST = 'http://farmreach.test';

    public function test_security_headers_are_added_to_web_responses(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_security_headers_are_added_to_api_responses(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_hsts_header_is_not_sent_outside_production(): void
    {
        $this->withServerVariables(self::PROXY_SERVER)
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get(self::APP_HOST.'/login')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_header_is_sent_for_production_https_responses(): void
    {
        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->withServerVariables(self::PROXY_SERVER)
                ->withHeader('X-Forwarded-Proto', 'https')
                ->get(self::APP_HOST.'/login')
                ->assertOk()
                ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public function test_hsts_header_is_not_sent_for_production_plain_http_responses(): void
    {
        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->withServerVariables(self::PROXY_SERVER)
                ->get(self::APP_HOST.'/login')
                ->assertOk()
                ->assertHeaderMissing('Strict-Transport-Security');
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }
}
