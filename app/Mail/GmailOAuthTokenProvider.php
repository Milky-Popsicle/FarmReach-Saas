<?php

namespace App\Mail;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\HttpClientException;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * Exchanges a Google OAuth refresh token for short-lived access tokens.
 *
 * Access tokens are cached until shortly before they expire. Refresh tokens,
 * client secrets, and access tokens are never written to logs or exceptions.
 */
class GmailOAuthTokenProvider
{
    /**
     * OAuth error codes that are safe to include in a transport exception.
     *
     * @var list<string>
     */
    private const SAFE_ERROR_CODES = [
        'access_denied',
        'invalid_client',
        'invalid_grant',
        'invalid_request',
        'invalid_scope',
        'unauthorized_client',
        'unsupported_grant_type',
    ];

    public function __construct(
        private readonly Factory $http,
        private readonly CacheRepository $cache,
        private readonly Encrypter $encrypter,
        private readonly string $clientId,
        #[\SensitiveParameter] private readonly string $clientSecret,
        #[\SensitiveParameter] private readonly string $refreshToken,
        private readonly string $tokenUri = 'https://oauth2.googleapis.com/token',
    ) {}

    /**
     * Return a valid Gmail API access token, refreshing it when necessary.
     *
     * @throws TransportException
     */
    public function accessToken(bool $forceRefresh = false): string
    {
        $this->ensureCredentialsAreConfigured();

        $cacheKey = $this->cacheKey();

        if (! $forceRefresh) {
            $cachedToken = $this->cache->get($cacheKey);

            if (is_string($cachedToken) && $cachedToken !== '') {
                try {
                    return $this->encrypter->decryptString($cachedToken);
                } catch (DecryptException) {
                    // Treat an unreadable cache entry as a cache miss.
                }
            }
        }

        try {
            $response = $this->http
                ->asForm()
                ->acceptJson()
                ->post($this->tokenUri, [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                ]);
        } catch (HttpClientException) {
            throw new TransportException(
                'The Gmail OAuth token refresh request could not be completed.'
            );
        }

        if (! $response->successful()) {
            $error = $response->json('error');
            $reason = is_string($error) && in_array($error, self::SAFE_ERROR_CODES, true)
                ? " ({$error})"
                : '';

            throw new TransportException(
                'Gmail OAuth token refresh failed with HTTP '.$response->status().$reason.'.'
            );
        }

        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new TransportException(
                'Gmail OAuth token refresh response did not contain an access token.'
            );
        }

        $expiresIn = (int) $response->json('expires_in', 3600);
        $ttl = max(60, ($expiresIn > 0 ? $expiresIn : 3600) - 60);

        $this->cache->put(
            $cacheKey,
            $this->encrypter->encryptString($accessToken),
            $ttl
        );

        return $accessToken;
    }

    /**
     * Remove the cached access token so the next call performs a refresh.
     */
    public function forget(): void
    {
        $this->cache->forget($this->cacheKey());
    }

    /**
     * @throws TransportException
     */
    private function ensureCredentialsAreConfigured(): void
    {
        $missing = [];

        if ($this->clientId === '') {
            $missing[] = 'GMAIL_OAUTH_CLIENT_ID';
        }

        if ($this->clientSecret === '') {
            $missing[] = 'GMAIL_OAUTH_CLIENT_SECRET';
        }

        if ($this->refreshToken === '') {
            $missing[] = 'GMAIL_OAUTH_REFRESH_TOKEN';
        }

        if ($missing !== []) {
            throw new TransportException(
                'Gmail OAuth credentials are not configured: '.implode(', ', $missing).'.'
            );
        }
    }

    private function cacheKey(): string
    {
        return 'gmail-oauth:access-token:'.substr(
            hash('sha256', $this->clientId.'|'.$this->refreshToken.'|'.$this->tokenUri),
            0,
            16
        );
    }
}
