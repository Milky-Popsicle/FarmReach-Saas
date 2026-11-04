<?php

namespace App\Mail\Transport;

use App\Mail\GmailOAuthTokenProvider;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\HttpClientException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * Sends Symfony/Laravel mail through the Gmail API using OAuth 2.0.
 *
 * The transport refreshes its access token before sending and retries once
 * after a 401 response. OAuth credentials and tokens are never logged.
 */
class GmailOAuthTransport implements TransportInterface
{
    public function __construct(
        private readonly Factory $http,
        private readonly GmailOAuthTokenProvider $tokens,
        private readonly string $apiUri = 'https://gmail.googleapis.com/gmail/v1',
    ) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $envelope ??= Envelope::create($message);

        // Validate the message before performing any network request.
        $sentMessage = new SentMessage($message, $envelope);

        $rawMessage = $this->rawMessageWithEnvelopeRecipients($message, $envelope);

        if ($rawMessage === '') {
            throw new TransportException(
                'Cannot send an empty email message through the Gmail API.'
            );
        }

        $payload = [
            'raw' => rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '='),
        ];

        $response = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $accessToken = $this->tokens->accessToken(forceRefresh: $attempt > 1);

            try {
                $response = $this->http
                    ->withToken($accessToken)
                    ->acceptJson()
                    ->post($this->apiUri.'/users/me/messages/send', $payload);
            } catch (HttpClientException) {
                throw new TransportException(
                    'The Gmail API send request could not be completed.'
                );
            }

            if ($response->successful()) {
                break;
            }

            if ($response->status() === 401 && $attempt === 1) {
                $this->tokens->forget();

                continue;
            }

            throw new TransportException(
                'Gmail API send failed with HTTP '.$response->status().'.'
            );
        }

        if ($response === null || ! $response->successful()) {
            throw new TransportException(
                'Gmail API send failed after refreshing the access token.'
            );
        }

        $messageId = $response->json('id');

        if (is_string($messageId) && $messageId !== '') {
            $sentMessage->setMessageId($messageId);
        }

        return $sentMessage;
    }

    /**
     * Build the RFC 2822 message Gmail should deliver.
     *
     * Gmail only delivers to addresses found in the To, Cc, and Bcc headers.
     * Symfony deliberately removes the Bcc header when serializing a message,
     * so envelope recipients that are not in the raw To/Cc headers must be
     * restored as Bcc. Gmail removes the Bcc header before delivery.
     */
    private function rawMessageWithEnvelopeRecipients(RawMessage $message, Envelope $envelope): string
    {
        $rawMessage = $message->toString();

        if ($rawMessage === '') {
            return '';
        }

        $missingRecipients = $this->missingEnvelopeRecipients($message, $envelope);

        if ($missingRecipients === []) {
            return $rawMessage;
        }

        $bccHeader = (new Headers)
            ->addMailboxListHeader('Bcc', $missingRecipients)
            ->toString();

        [$insertAt, $newline] = $this->headerBlockEnd($rawMessage);

        if ($insertAt === null) {
            return $bccHeader."\r\n".$rawMessage;
        }

        if ($newline !== "\r\n") {
            $bccHeader = str_replace("\r\n", $newline, $bccHeader);
        }

        return substr($rawMessage, 0, $insertAt)
            .$bccHeader
            .substr($rawMessage, $insertAt);
    }

    /**
     * @return Address[]
     */
    private function missingEnvelopeRecipients(RawMessage $message, Envelope $envelope): array
    {
        if (! $message instanceof Message) {
            return $envelope->getRecipients();
        }

        /** @var array<string, true> $present */
        $present = [];

        foreach (['to', 'cc'] as $headerName) {
            foreach ($message->getHeaders()->all($headerName) as $header) {
                if (! $header instanceof MailboxListHeader) {
                    continue;
                }

                foreach ($header->getAddresses() as $address) {
                    $present[strtolower($address->getAddress())] = true;
                }
            }
        }

        /** @var Address[] $missing */
        $missing = [];

        foreach ($envelope->getRecipients() as $recipient) {
            $key = strtolower($recipient->getAddress());

            if (isset($present[$key])) {
                continue;
            }

            $missing[] = $recipient;
            $present[$key] = true;
        }

        return $missing;
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    private function headerBlockEnd(string $rawMessage): array
    {
        $separator = strpos($rawMessage, "\r\n\r\n");
        $newline = "\r\n";

        if ($separator === false) {
            $separator = strpos($rawMessage, "\n\n");
            $newline = "\n";
        }

        if ($separator === false) {
            return [null, $newline];
        }

        return [$separator + strlen($newline), $newline];
    }

    public function __toString(): string
    {
        return 'gmail-oauth';
    }
}
