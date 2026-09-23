<?php

namespace App\Modules\DataIngestion\Infrastructure\Parsers;

/**
 * Streaming JSON parser.
 * Supports two formats:
 *   1. JSON Lines (JSONL) — one JSON object per line.
 *   2. JSON Array  — a top-level array `[{...}, {...}]`.
 *
 * Strategy: peek at the first non-whitespace byte.
 *   - If `[` → read whole file once (JSON Array).
 *   - Otherwise → stream line by line (JSONL).
 *
 * For very large JSON Arrays consider a streaming JSON library; this
 * implementation covers the project's current scale requirements.
 */
final class JsonImportParser implements ImportParserInterface
{
    /**
     * @return iterable<int, array<string, string>>
     */
    public function parse(string $filePath): iterable
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        try {
            // Peek at first non-whitespace character
            $firstChar = $this->peekFirstNonWhitespace($handle);

            if ($firstChar === '[') {
                yield from $this->parseJsonArray($filePath);
            } else {
                yield from $this->parseJsonLines($handle);
            }
        } finally {
            fclose($handle);
        }
    }

    private function peekFirstNonWhitespace(mixed $handle): string
    {
        $pos = ftell($handle);
        $char = '';
        while (! feof($handle)) {
            $byte = fread($handle, 1);
            if ($byte === false) {
                break;
            }
            if (trim($byte) !== '') {
                $char = $byte;
                break;
            }
        }
        fseek($handle, $pos);

        return $char;
    }

    /**
     * Parse a JSON Array file (loaded once — acceptable for array format).
     *
     * @return iterable<int, array<string, string>>
     */
    private function parseJsonArray(string $filePath): iterable
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file: {$filePath}");
        }

        /** @var mixed $decoded */
        $decoded = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            return;
        }

        foreach ($decoded as $row) {
            if (is_array($row)) {
                /** @var array<string, string> $row */
                yield $row;
            }
        }
    }

    /**
     * Stream a JSON Lines file line by line.
     *
     * @param  resource  $handle
     * @return iterable<int, array<string, string>>
     */
    private function parseJsonLines(mixed $handle): iterable
    {
        rewind($handle);
        while (! feof($handle)) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            /** @var mixed $row */
            $row = json_decode($line, associative: true, flags: JSON_THROW_ON_ERROR);
            if (is_array($row)) {
                /** @var array<string, string> $row */
                yield $row;
            }
        }
    }
}
