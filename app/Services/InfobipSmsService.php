<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class InfobipSmsService
{
    /**
     * Submit one SMS through Infobip. A successful API response means accepted
     * for processing; delivery to the handset is still asynchronous.
     */
    public function send(string $recipient, string $message): void
    {
        $baseUrl = trim((string) config('services.infobip.base_url'));
        $apiKey = trim((string) config('services.infobip.api_key'));
        $sender = trim((string) config('services.infobip.sender', 'InfoSMS'));

        if ($baseUrl === '' || $apiKey === '' || $sender === '') {
            throw new RuntimeException('Infobip SMS configuration is incomplete.');
        }

        if (! str_starts_with($baseUrl, 'https://')) {
            $baseUrl = 'https://'.$baseUrl;
        }

        $url = rtrim($baseUrl, '/').'/sms/3/messages';

        $response = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'App '.$apiKey,
            ])
            ->timeout(15)
            ->post($url, [
                'messages' => [
                    [
                        'sender' => $sender,
                        'destinations' => [
                            ['to' => $recipient],
                        ],
                        'content' => [
                            'text' => $message,
                        ],
                    ],
                ],
            ]);

        $response->throw();

        if (! is_string(data_get($response->json(), 'messages.0.messageId'))) {
            throw new RuntimeException('Infobip did not return an SMS message ID.');
        }
    }
}
