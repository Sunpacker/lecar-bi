<?php

namespace App\Modules\DataIngestion\Infrastructure\Parsers;

interface ImportParserInterface
{
    /**
     * Parse a file and yield each data row as an associative array.
     * Implementations MUST stream the file (not load it entirely into memory).
     *
     * @return iterable<int, array<string, string>>
     */
    public function parse(string $filePath): iterable;
}
