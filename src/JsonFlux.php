<?php

declare(strict_types=1);

namespace JsonFlux;

use JsonFlux\Exceptions\ParseException;

/**
 * Main entry point and unified facade for the JsonFlux library.
 */
class JsonFlux
{
    /**
     * Resiliently repair malformed, truncated, or unclosed JSON text.
     *
     * @param string $json Malformed JSON input
     * @return string Valid standard JSON string
     * @throws ParseException
     */
    public static function repair(string $json): string
    {
        return JsonRepair::repair($json);
    }

    /**
     * Safely decodes a JSON string into PHP structures.
     * Automatically attempts to repair malformed or truncated input if standard decoding fails.
     *
     * @param string $json Raw or partial JSON string
     * @param bool $assoc Return associative array instead of stdClass
     * @return mixed Decoded data
     * @throws ParseException
     */
    public static function decode(string $json, bool $assoc = true): mixed
    {
        $trimmed = trim($json);
        if ($trimmed === '') {
            return $assoc ? [] : new \stdClass();
        }

        // Fast path: standard json_decode
        try {
            return json_decode($trimmed, $assoc, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Repair and retry
            $repaired = JsonRepair::repair($trimmed);
            try {
                return json_decode($repaired, $assoc, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new ParseException('Failed to decode repaired JSON: ' . $e->getMessage(), -1, $repaired, $e);
            }
        }
    }

    /**
     * Helper to encode data into clean JSON with unescaped unicode and slashes.
     */
    public static function encode(mixed $value, int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($value, $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Check if a string is strictly valid JSON without repair.
     */
    public static function isValid(string $json): bool
    {
        return JsonRepair::isValidJson($json);
    }

    /**
     * Extract the first valid or repairable JSON document from markdown or prose text.
     */
    public static function extract(string $text, bool $assoc = true): mixed
    {
        return Extractor::extractFirst($text, $assoc);
    }

    /**
     * Extract all JSON documents from markdown, tags, or text.
     */
    public static function extractAll(string $text, bool $assoc = true): array
    {
        return Extractor::extractAll($text, $assoc);
    }

    /**
     * Instantiate a new progressive StreamParser.
     */
    public static function stream(string $initial = ''): StreamParser
    {
        return new StreamParser($initial);
    }

    /**
     * Instantiate a new Server-Sent Events (SSE) parser.
     */
    public static function sse(string $initial = ''): SSEParser
    {
        return new SSEParser($initial);
    }
}
