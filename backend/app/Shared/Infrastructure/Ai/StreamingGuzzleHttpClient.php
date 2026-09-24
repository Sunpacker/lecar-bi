<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Ai;

use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\HttpClient\HttpRequest;
use NeuronAI\HttpClient\StreamInterface;

final class StreamingGuzzleHttpClient extends GuzzleHttpClient
{
    public function stream(HttpRequest $request): StreamInterface
    {
        $url = trim($this->baseUri, '/').'/'.trim($request->uri, '/').'?alt=sse';

        return new CurlMultiStream(
            $url,
            $request,
            [...$this->customHeaders, 'Accept-Encoding' => 'identity'],
            $this->timeout,
            $this->connectTimeout,
        );
    }
}
