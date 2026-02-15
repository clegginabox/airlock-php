<?php

declare(strict_types=1);

namespace App\Examples\TrafficControl;

/**
 * Minimal fire-and-forget Centrifugo HTTP API client.
 *
 * Uses fsockopen to write the request without waiting for a response,
 * so it doesn't block the Amp event loop.
 */
final class CentrifugoClient
{
    public function __construct(
        private readonly string $host = 'centrifugo',
        private readonly int $port = 8000,
        private readonly string $apiKey = 'airlock-demo-api-key',
    ) {
    }

    public function publish(string $channel, string $jsonData): void
    {
        $payload = json_encode([
            'channel' => $channel,
            'data' => json_decode($jsonData, true),
        ], JSON_THROW_ON_ERROR);

        $fp = @fsockopen($this->host, $this->port, $errno, $errstr, 1);
        if (!$fp) {
            return;
        }

        $request = "POST /api/publish HTTP/1.1\r\n"
            . "Host: {$this->host}:{$this->port}\r\n"
            . "Content-Type: application/json\r\n"
            . "X-API-Key: {$this->apiKey}\r\n"
            . "Content-Length: " . strlen($payload) . "\r\n"
            . "Connection: close\r\n"
            . "\r\n"
            . $payload;

        fwrite($fp, $request);
        fclose($fp);
    }
}
