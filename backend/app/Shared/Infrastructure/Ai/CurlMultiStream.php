<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Ai;

use CurlHandle;
use CurlMultiHandle;
use NeuronAI\HttpClient\HttpRequest;
use NeuronAI\HttpClient\StreamInterface;
use RuntimeException;

final class CurlMultiStream implements StreamInterface
{
    private string $buffer = '';

    private bool $complete = false;

    private bool $closed = false;

    private readonly CurlHandle $handle;

    private readonly CurlMultiHandle $multiHandle;

    /** @param array<string, string> $headers */
    public function __construct(string $url, HttpRequest $request, array $headers, float $timeout, float $connectTimeout)
    {
        $this->handle = curl_init($url);
        $this->multiHandle = curl_multi_init();
        curl_setopt_array($this->handle, [
            CURLOPT_CUSTOMREQUEST => $request->method->value,
            CURLOPT_HTTPHEADER => $this->formatHeaders([...$headers, ...$request->headers]),
            CURLOPT_POSTFIELDS => is_array($request->body) ? json_encode($request->body, JSON_THROW_ON_ERROR) : $request->body,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT_MS => (int) ($timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) ($connectTimeout * 1000),
            CURLOPT_WRITEFUNCTION => function (CurlHandle $handle, string $data): int {
                $this->buffer .= $data;

                return strlen($data);
            },
        ]);
        curl_multi_add_handle($this->multiHandle, $this->handle);
    }

    public function eof(): bool
    {
        $this->pump(1);

        return $this->complete && $this->buffer === '';
    }

    public function read(int $length): string
    {
        $this->pump($length);
        $result = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, strlen($result));

        return $result;
    }

    public function readLine(): string
    {
        $line = '';
        while (! $this->eof()) {
            $character = $this->read(1);
            $line .= $character;
            if ($character === "\n") {
                break;
            }
        }

        return $line;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        if (! $this->complete) {
            curl_multi_remove_handle($this->multiHandle, $this->handle);
        }
        $this->complete = true;
        $this->closed = true;
        $this->buffer = '';
        curl_multi_close($this->multiHandle);
        curl_close($this->handle);
    }

    public function __destruct()
    {
        $this->close();
    }

    private function pump(int $minimumBytes): void
    {
        while (! $this->complete && strlen($this->buffer) < $minimumBytes) {
            do {
                $status = curl_multi_exec($this->multiHandle, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            if ($status !== CURLM_OK) {
                throw new RuntimeException('Google streaming transport failed');
            }
            if ($running === 0) {
                $this->finish();

                continue;
            }

            if (curl_multi_select($this->multiHandle, 1.0) === -1) {
                usleep(1000);
            }
        }
    }

    private function finish(): void
    {
        $errorCode = curl_errno($this->handle);
        $statusCode = (int) curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE);
        curl_multi_remove_handle($this->multiHandle, $this->handle);
        $this->complete = true;
        if ($errorCode !== CURLE_OK || $statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException("Google streaming transport failed with HTTP {$statusCode} and cURL code {$errorCode}");
        }
    }

    /**
     * @param  array<string, string>  $headers
     * @return list<string>
     */
    private function formatHeaders(array $headers): array
    {
        return array_map(static fn (string $value, string $name): string => $name.': '.$value, $headers, array_keys($headers));
    }
}
