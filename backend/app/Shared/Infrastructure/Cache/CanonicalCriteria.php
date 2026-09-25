<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cache;

final class CanonicalCriteria
{
    /**
     * Produces a deterministic SHA-256 hash of criteria arguments or DTOs.
     */
    public static function toHash(mixed $criteria): string
    {
        $normalized = self::normalize($criteria);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $json !== false ? $json : '');
    }

    /**
     * Recursively normalizes criteria objects and arrays into deterministic, sorted data structures.
     */
    public static function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return $value;
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            $isAssoc = ! array_is_list($value);
            $normalized = [];

            foreach ($value as $k => $v) {
                $normalized[$k] = self::normalize($v);
            }

            if ($isAssoc) {
                ksort($normalized, SORT_STRING);
            }

            return $normalized;
        }

        return (string) $value;
    }
}
