<?php

declare(strict_types=1);

namespace Nexus\Http;

use Nexus\Exception\ProviderException;

/**
 * Thin cURL wrapper for synchronous and streamed HTTP requests.
 *
 * @package Nexus\Http
 */
final class Client
{
    private ?\CurlHandle $handle = null;

    /**
     * @param int $timeout        Default request timeout in seconds.
     * @param int $connectTimeout TCP connect timeout in seconds.
     */
    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
    ) {
    }

    public function __destruct()
    {
        if ($this->handle !== null) {
            curl_close($this->handle);
        }
    }

    /**
     * Execute a blocking HTTP request.
     *
     * @param string                       $method  HTTP verb (GET, POST, etc.).
     * @param string                       $url     Fully-qualified URL.
     * @param array<string, string>|string[] $headers Associative or indexed header list.
     * @param string|null                  $body    Request body.
     * @param int|null                     $timeout Per-request timeout override in seconds.
     *
     * @return array{status: int, body: string, headers: array<string, string>}
     *
     * @throws ProviderException On cURL failure.
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        ?int $timeout = null,
    ): array {
        $ch = $this->getHandle();

        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_TIMEOUT => $timeout ?? $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => 60,
            CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$responseHeaders) {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($header);
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            throw new ProviderException(
                'HTTP request failed: ' . curl_error($ch),
                statusCode: 0,
            );
        }

        return [
            'status' => $statusCode,
            'body' => (string) $response,
            'headers' => $responseHeaders,
        ];
    }

    /**
     * Execute a streaming HTTP request, invoking a callback for each data chunk.
     *
     * @param string                         $method  HTTP verb.
     * @param string                         $url     Fully-qualified URL.
     * @param array<string, string>|string[] $headers Associative or indexed header list.
     * @param string|null                    $body    Request body.
     * @param (callable(string): void)|null  $onChunk Callback receiving each raw chunk.
     * @param int|null                       $timeout Per-request timeout override in seconds.
     *
     * @throws ProviderException On cURL failure.
     */
    public function stream(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        callable $onChunk = null,
        ?int $timeout = null,
    ): void {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_TIMEOUT => $timeout ?? max($this->timeout, 120),
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($onChunk) {
                if ($onChunk !== null) {
                    $onChunk($data);
                }
                return strlen($data);
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new ProviderException('Stream request failed: ' . $error);
        }

        curl_close($ch);
    }

    /**
     * @return \CurlHandle Reusable handle, reset between requests.
     */
    private function getHandle(): \CurlHandle
    {
        if ($this->handle === null) {
            $this->handle = curl_init();
        }

        curl_reset($this->handle);

        return $this->handle;
    }

    /**
     * Normalize mixed header array into cURL's indexed "Key: Value" format.
     *
     * @param array<int|string, string> $headers
     *
     * @return string[]
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $key => $value) {
            $formatted[] = is_int($key) ? $value : "{$key}: {$value}";
        }

        return $formatted;
    }
}
