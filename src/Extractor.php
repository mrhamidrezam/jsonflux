<?php

declare(strict_types=1);

namespace JsonFlux;

use JsonFlux\Exceptions\ParseException;

/**
 * Extracts and decodes JSON structures embedded within unstructured text,
 * markdown fences, or XML-style tags produced by LLMs.
 */
class Extractor
{
    /**
     * Extract the first valid or repairable JSON document from text.
     *
     * @param string $text Markdown or raw conversational text
     * @param bool $assoc Return associative array or stdClass
     * @return mixed Decoded JSON structure
     * @throws ParseException If no valid/repairable JSON document is found
     */
    public static function extractFirst(string $text, bool $assoc = true): mixed
    {
        $all = self::extractAll($text, $assoc);
        if (empty($all)) {
            throw new ParseException('No JSON document could be extracted from input text');
        }
        return $all[0];
    }

    /**
     * Extract all JSON structures found in text (markdown blocks, XML tags, or raw objects/arrays).
     *
     * @param string $text
     * @param bool $assoc
     * @return array<mixed> List of decoded JSON objects/arrays
     */
    public static function extractAll(string $text, bool $assoc = true): array
    {
        $results = [];

        // 1. First, search for markdown code fences (```json ... ``` or ``` ... ```)
        if (preg_match_all('/```(?:json)?\s*([\s\S]*?)\s*```/i', $text, $matches)) {
            foreach ($matches[1] as $block) {
                try {
                    $repaired = JsonRepair::repair($block);
                    $results[] = json_decode($repaired, $assoc, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    // Try next block
                }
            }
        }

        // If markdown blocks found, return them
        if (!empty($results)) {
            return $results;
        }

        // 2. Search for XML-style tags like <tool_call>...</tool_call> or <json>...</json>
        if (preg_match_all('/<([a-zA-Z0-9_\-]+)>\s*([\s\S]*?)\s*<\/\1>/', $text, $tagMatches)) {
            foreach ($tagMatches[2] as $block) {
                try {
                    $repaired = JsonRepair::repair($block);
                    $results[] = json_decode($repaired, $assoc, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    // Continue
                }
            }
        }

        if (!empty($results)) {
            return $results;
        }

        // 3. Scan for balanced curly braces { ... } and square brackets [ ... ]
        $candidates = self::findJsonCandidates($text);
        foreach ($candidates as $candidate) {
            try {
                $repaired = JsonRepair::repair($candidate);
                $decoded = json_decode($repaired, $assoc, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded) || is_object($decoded)) {
                    $results[] = $decoded;
                }
            } catch (\Throwable) {
                // Continue searching
            }
        }

        return $results;
    }

    /**
     * Extract JSON content enclosed by specific XML-like tags (e.g. 'tool_call', 'function_call').
     *
     * @return array<mixed>
     */
    public static function extractByTag(string $text, string $tagName, bool $assoc = true): array
    {
        $pattern = sprintf('/<%s>\s*([\s\S]*?)\s*<\/%s>/i', preg_quote($tagName, '/'), preg_quote($tagName, '/'));
        $results = [];

        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $block) {
                try {
                    $repaired = JsonRepair::repair($block);
                    $results[] = json_decode($repaired, $assoc, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    // Continue
                }
            }
        }

        return $results;
    }

    /**
     * Scans unstructured text for potential JSON objects and arrays using bracket depth matching.
     *
     * @return array<string>
     */
    private static function findJsonCandidates(string $text): array
    {
        $len = strlen($text);
        $candidates = [];

        for ($i = 0; $i < $len; $i++) {
            $char = $text[$i];

            if ($char === '{' || $char === '[') {
                $closeTarget = ($char === '{') ? '}' : ']';
                $depth = 1;
                $inQuote = false;
                $escaped = false;
                $start = $i;

                for ($j = $i + 1; $j < $len; $j++) {
                    $c = $text[$j];

                    if ($inQuote) {
                        if ($escaped) {
                            $escaped = false;
                        } elseif ($c === '\\') {
                            $escaped = true;
                        } elseif ($c === '"') {
                            $inQuote = false;
                        }
                        continue;
                    }

                    if ($c === '"') {
                        $inQuote = true;
                        continue;
                    }

                    if ($c === $char) {
                        $depth++;
                    } elseif ($c === $closeTarget) {
                        $depth--;
                        if ($depth === 0) {
                            $candidates[] = substr($text, $start, $j - $start + 1);
                            $i = $j; // advance outer pointer
                            break;
                        }
                    }
                }
            }
        }

        return $candidates;
    }
}
