<?php

namespace App\Modules\DataIngestion\Infrastructure\Parsers;

use SplFileObject;

/**
 * Streaming CSV parser using SplFileObject.
 * Reads the file row-by-row — does NOT load the entire file into memory.
 */
final class CsvImportParser implements ImportParserInterface
{
    public function __construct(
        private readonly string $delimiter = ',',
        private readonly string $enclosure = '"',
        private readonly string $escape = '\\'
    ) {}

    /**
     * @return iterable<int, array<string, string>>
     */
    public function parse(string $filePath): iterable
    {
        $file = new SplFileObject($filePath, 'r');
        $file->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::DROP_NEW_LINE
        );
        $file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

        $rawHeaders = $file->current();
        if (! is_array($rawHeaders)) {
            return;
        }

        /** @var list<string> $headers */
        $headers = array_map(static fn ($v) => trim((string) $v), $rawHeaders);
        $file->next();

        while (! $file->eof()) {
            $rawRow = $file->current();
            $file->next();

            if (! is_array($rawRow)) {
                continue;
            }

            // Skip rows that are just a single null element (empty line artefact)
            /** @var list<string|null> $rawRow */
            if (count($rawRow) === 1 && $rawRow[0] === null) {
                continue;
            }

            $values = array_map(static fn ($v) => trim((string) $v), $rawRow);

            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = $values[$i] ?? '';
            }

            yield $row;
        }
    }
}
