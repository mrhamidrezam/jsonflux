<?php

declare(strict_types=1);

namespace JsonFlux;

use Generator;

/**
 * Server-Sent Events (SSE) stream processor for streaming LLM HTTP responses.
 *
 * Buffers partial network chunks, standardizes SSE frames, parses `data:` lines,
 * handles `[DONE]` stream terminations, and extracts JSON objects seamlessly.
 */
class SSEParser
{
    private string $buffer = '';
    private array $messageListeners = [];
    private bool $isDone = false;

    public function __construct(string $initial = '')
    {
        if ($initial !== '') {
            $this->feed($initial);
        }
    }

    /**
     * Feed an incoming chunk of SSE bytes/text into the parser.
     *
     * @return array<array{event: string, data: string, id: ?string}> Parsed SSE messages in this chunk
     */
    public function feed(string $chunk): array
    {
        if ($chunk === '') {
            return [];
        }

        $this->buffer .= $chunk;
        $messages = [];

        // SSE messages are delimited by double newlines (\n\n or \r\n\r\n)
        while (($pos = $this->findEventBoundary($this->buffer)) !== false) {
            $rawEvent = substr($this->buffer, 0, $pos['end']);
            $this->buffer = substr($this->buffer, $pos['next']);

            $parsed = $this->parseEventBlock($rawEvent);
            if ($parsed !== null) {
                if ($parsed['data'] === '[DONE]') {
                    $this->isDone = true;
                }

                $messages[] = $parsed;

                foreach ($this->messageListeners as $listener) {
                    $listener($parsed, $this);
                }
            }
        }

        return $messages;
    }

    /**
     * Check if the stream has encountered the terminal `[DONE]` marker.
     */
    public function isDone(): bool
    {
        return $this->isDone;
    }

    /**
     * Register a callback for incoming SSE messages.
     *
     * @param callable(array{event: string, data: string, id: ?string}, SSEParser): void $callback
     */
    public function onMessage(callable $callback): self
    {
        $this->messageListeners[] = $callback;
        return $this;
    }

    /**
     * Generator yielding decoded JSON payloads from all `data:` lines in given text or stream chunks.
     *
     * @param iterable<string>|string $chunks
     * @return Generator<int, array>
     */
    public function extractJson(iterable|string $chunks): Generator
    {
        if (is_string($chunks)) {
            $chunks = [$chunks];
        }

        foreach ($chunks as $chunk) {
            $messages = $this->feed($chunk);
            foreach ($messages as $msg) {
                if ($msg['data'] === '' || $msg['data'] === '[DONE]') {
                    continue;
                }

                try {
                    $repaired = JsonRepair::repair($msg['data']);
                    yield json_decode($repaired, true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    // Ignore non-json data frames
                }
            }
        }
    }

    /**
     * Reset internal buffer.
     */
    public function reset(): self
    {
        $this->buffer = '';
        $this->isDone = false;
        return $this;
    }

    private function findEventBoundary(string $text): array|false
    {
        $r1 = strpos($text, "\n\n");
        $r2 = strpos($text, "\r\n\r\n");

        if ($r1 === false && $r2 === false) {
            return false;
        }

        if ($r1 !== false && $r2 !== false) {
            if ($r1 < $r2) {
                return ['end' => $r1, 'next' => $r1 + 2];
            }
            return ['end' => $r2, 'next' => $r2 + 4];
        }

        if ($r1 !== false) {
            return ['end' => $r1, 'next' => $r1 + 2];
        }

        return ['end' => $r2, 'next' => $r2 + 4];
    }

    private function parseEventBlock(string $block): ?array
    {
        $lines = preg_split('/\r?\n/', $block);
        if ($lines === false) {
            return null;
        }

        $event = 'message';
        $dataLines = [];
        $id = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Comments in SSE start with colon
            if ($line === '' || str_starts_with($line, ':')) {
                continue;
            }

            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $dataLines[] = ltrim(substr($line, 5));
            } elseif (str_starts_with($line, 'id:')) {
                $id = trim(substr($line, 3));
            }
        }

        if (empty($dataLines)) {
            return null;
        }

        return [
            'event' => $event,
            'data' => implode("\n", $dataLines),
            'id' => $id,
        ];
    }
}
