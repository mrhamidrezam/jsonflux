<?php

declare(strict_types=1);

namespace JsonFlux;

use JsonFlux\Exceptions\ParseException;

/**
 * Resilient JSON repair engine.
 *
 * Automatically fixes truncated JSON, unclosed strings, dangling commas,
 * unquoted keys, single quotes, Python literals, and trailing syntax artifacts
 * commonly produced by LLMs and streaming APIs.
 */
class JsonRepair
{
    /**
     * Attempts to repair malformed or truncated JSON string into valid, parseable JSON.
     *
     * @param string $input Raw JSON text (may be truncated or syntactically broken)
     * @return string Repaired, standard JSON string
     * @throws ParseException If input contains completely unparseable content
     */
    public static function repair(string $input): string
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return '{}';
        }

        // Fast-path: check if input is already valid JSON
        if (self::isValidJson($trimmed)) {
            return $trimmed;
        }

        // Strip markdown fences if wrapping the entire string
        $cleaned = self::stripMarkdownFences($trimmed);
        if (self::isValidJson($cleaned)) {
            return $cleaned;
        }

        // Strip line and block comments (outside strings)
        $cleaned = self::stripComments($cleaned);

        // Normalize Python literals (True, False, None) and unquoted keys / single quotes
        $normalized = self::normalizeLiteralsAndQuotes($cleaned);

        // Balance and close open tokens / arrays / objects
        $balanced = self::balanceAndClose($normalized);

        // Final verification
        if (self::isValidJson($balanced)) {
            return $balanced;
        }

        // Secondary fallback pass: aggressive truncation repair
        $aggressive = self::aggressiveRepair($balanced);
        if (self::isValidJson($aggressive)) {
            return $aggressive;
        }

        // If still invalid, throw informative exception
        throw new ParseException('Unable to repair malformed JSON input', 0, substr($input, 0, 80));
    }

    /**
     * Check if a string is strictly valid JSON.
     */
    public static function isValidJson(string $json): bool
    {
        if ($json === '') {
            return false;
        }
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Strips leading and trailing markdown code block fences (e.g. ```json ... ```).
     */
    private static function stripMarkdownFences(string $text): string
    {
        $text = trim($text);
        if (str_starts_with($text, '```')) {
            // Find end of first line
            $firstNewline = strpos($text, "\n");
            if ($firstNewline !== false) {
                $text = substr($text, $firstNewline + 1);
            } else {
                $text = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $text) ?? $text;
            }
        }
        if (str_ends_with($text, '```')) {
            $text = substr($text, 0, -3);
        }
        return trim($text);
    }

    /**
     * Strips single-line and multi-line comments that are outside quotes.
     */
    private static function stripComments(string $text): string
    {
        $len = strlen($text);
        $result = '';
        $inQuote = false;
        $quoteChar = '';
        $escaped = false;

        for ($i = 0; $i < $len; $i++) {
            $char = $text[$i];

            if ($inQuote) {
                $result .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $quoteChar) {
                    $inQuote = false;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $inQuote = true;
                $quoteChar = $char;
                $result .= $char;
                continue;
            }

            // Check for // comment
            if ($char === '/' && $i + 1 < $len && $text[$i + 1] === '/') {
                $nextNewline = strpos($text, "\n", $i);
                if ($nextNewline === false) {
                    break;
                }
                $i = $nextNewline;
                $result .= "\n";
                continue;
            }

            // Check for /* */ comment
            if ($char === '/' && $i + 1 < $len && $text[$i + 1] === '*') {
                $closeComment = strpos($text, '*/', $i + 2);
                if ($closeComment === false) {
                    break;
                }
                $i = $closeComment + 1;
                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    /**
     * Normalizes single quotes to double quotes, fixes Python literals,
     * and handles unquoted object keys outside strings.
     */
    private static function normalizeLiteralsAndQuotes(string $text): string
    {
        $len = strlen($text);
        $result = '';
        $inDoubleQuote = false;
        $inSingleQuote = false;
        $escaped = false;
        $buffer = '';

        for ($i = 0; $i < $len; $i++) {
            $char = $text[$i];

            if ($inDoubleQuote) {
                if ($escaped) {
                    $escaped = false;
                    $result .= $char;
                } elseif ($char === '\\') {
                    $escaped = true;
                    $result .= $char;
                } elseif ($char === '"') {
                    $inDoubleQuote = false;
                    $result .= $char;
                } elseif ($char === "\n") {
                    $result .= "\\n";
                } elseif ($char === "\r") {
                    $result .= "\\r";
                } elseif ($char === "\t") {
                    $result .= "\\t";
                } else {
                    $result .= $char;
                }
                continue;
            }

            if ($inSingleQuote) {
                if ($escaped) {
                    $escaped = false;
                    $result .= $char;
                } elseif ($char === '\\') {
                    $escaped = true;
                    $result .= $char;
                } elseif ($char === "'") {
                    $inSingleQuote = false;
                    $result .= '"'; // Convert closing single quote to double quote
                } elseif ($char === '"') {
                    $result .= '\\"'; // Escape inner double quotes
                } elseif ($char === "\n") {
                    $result .= "\\n";
                } elseif ($char === "\r") {
                    $result .= "\\r";
                } elseif ($char === "\t") {
                    $result .= "\\t";
                } else {
                    $result .= $char;
                }
                continue;
            }

            // Not inside any quote:
            if ($char === '"') {
                $inDoubleQuote = true;
                $result .= $char;
                continue;
            }

            if ($char === "'") {
                $inSingleQuote = true;
                $result .= '"'; // Convert opening single quote to double quote
                continue;
            }

            $result .= $char;
        }

        // Replace Python literals outside strings
        $result = preg_replace_callback(
            '/\b(True|False|None)\b/',
            function ($matches) {
                return match ($matches[1]) {
                    'True' => 'true',
                    'False' => 'false',
                    'None' => 'null',
                    default => $matches[1]
                };
            },
            $result
        ) ?? $result;

        // Fix unquoted object keys (e.g. { foo: "bar", baz_123: 456 })
        $result = preg_replace('/([{,]\s*)([a-zA-Z_][a-zA-Z0-9_\-]*)\s*:/', '$1"$2":', $result) ?? $result;

        return $result;
    }

    /**
     * Balances open brackets/braces, closes unclosed strings,
     * strips trailing commas, and handles dangling colons.
     */
    private static function balanceAndClose(string $text): string
    {
        $len = strlen($text);
        $stack = [];
        $inQuote = false;
        $escaped = false;
        $output = '';

        for ($i = 0; $i < $len; $i++) {
            $char = $text[$i];

            if ($inQuote) {
                $output .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inQuote = false;
                }
                continue;
            }

            if ($char === '"') {
                $inQuote = true;
                $output .= $char;
                continue;
            }

            if ($char === '{') {
                $stack[] = '}';
                $output .= $char;
            } elseif ($char === '[') {
                $stack[] = ']';
                $output .= $char;
            } elseif ($char === '}' || $char === ']') {
                if (!empty($stack)) {
                    $expected = array_pop($stack);
                    // If matched or mismatched, keep expected closure
                    $output .= $char;
                }
            } else {
                $output .= $char;
            }
        }

        // If string was truncated mid-quote, close it
        if ($inQuote) {
            $output .= '"';
        }

        // Strip trailing whitespaces
        $output = rtrim($output);

        // Fix dangling colon at the end, e.g. `{"key":` -> `{"key": null`
        if (str_ends_with($output, ':')) {
            $output .= ' null';
        }

        // Remove trailing commas before closing braces/brackets, e.g. `[1, 2, , ]` or `{"a": 1,}`
        $output = preg_replace('/,\s*$/', '', $output) ?? $output;
        $output = preg_replace('/,\s*(\]|\})/', '$1', $output) ?? $output;

        // Close remaining unclosed structures in reverse order
        while (!empty($stack)) {
            $closing = array_pop($stack);
            // Double check if trailing comma needs stripping before closing
            $output = rtrim($output);
            if (str_ends_with($output, ',')) {
                $output = substr($output, 0, -1);
            }
            if (str_ends_with($output, ':')) {
                $output .= ' null';
            }
            $output .= $closing;
        }

        // Final cleanup of trailing commas before closures that might have been formed
        $output = preg_replace('/,\s*(\]|\})/', '$1', $output) ?? $output;

        return $output;
    }

    /**
     * Aggressive fallback when standard balancing leaves isolated fragments.
     */
    private static function aggressiveRepair(string $text): string
    {
        $trimmed = trim($text);

        // If it starts with neither { nor [, find the first occurrence
        $firstBrace = strpos($trimmed, '{');
        $firstBracket = strpos($trimmed, '[');

        $startIdx = -1;
        if ($firstBrace !== false && $firstBracket !== false) {
            $startIdx = min($firstBrace, $firstBracket);
        } elseif ($firstBrace !== false) {
            $startIdx = $firstBrace;
        } elseif ($firstBracket !== false) {
            $startIdx = $firstBracket;
        }

        if ($startIdx > 0) {
            $trimmed = substr($trimmed, $startIdx);
        }

        // Balance again on the sliced payload
        return self::balanceAndClose($trimmed);
    }
}
