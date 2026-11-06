<?php

declare(strict_types=1);

namespace JsonFlux;

use Closure;
use JsonFlux\Exceptions\ParseException;

/**
 * Progressive streaming JSON parser.
 *
 * Consumes continuous token chunks from streaming LLMs (OpenAI, Gemini, Anthropic, Ollama),
 * allows querying partial values mid-stream, and triggers event hooks.
 */
class StreamParser
{
    private string $buffer = '';
    private mixed $lastPartialSnapshot = null;
    private array $chunkListeners = [];
    private array $keyListeners = [];
    private array $completeListeners = [];
    private array $knownValues = [];
    private bool $isFinished = false;

    public function __construct(string $initial = '')
    {
        if ($initial !== '') {
            $this->feed($initial);
        }
    }

    /**
     * Feed a new text/token chunk into the parser stream.
     */
    public function feed(string $chunk): self
    {
        if ($chunk === '') {
            return $this;
        }

        $this->buffer .= $chunk;

        // Notify raw chunk listeners
        foreach ($this->chunkListeners as $listener) {
            $listener($chunk, $this);
        }

        // Check if any key listeners need to be triggered
        if (!empty($this->keyListeners)) {
            $this->evaluateKeyListeners();
        }

        return $this;
    }

    /**
     * Check if the current buffer forms a strictly valid, completed JSON document.
     */
    public function isComplete(): bool
    {
        return JsonRepair::isValidJson($this->buffer);
    }

    /**
     * Get the current snapshot of parsed data, even if stream is incomplete.
     * Uses JsonRepair to safely close open structures.
     *
     * @param bool $assoc Return associative array or stdClass
     */
    public function getPartial(bool $assoc = true): mixed
    {
        if (trim($this->buffer) === '') {
            return $assoc ? [] : new \stdClass();
        }

        try {
            $repaired = JsonRepair::repair($this->buffer);
            $decoded = json_decode($repaired, $assoc, 512, JSON_THROW_ON_ERROR);
            $this->lastPartialSnapshot = $decoded;
            return $decoded;
        } catch (\Throwable) {
            return $this->lastPartialSnapshot ?? ($assoc ? [] : new \stdClass());
        }
    }

    /**
     * Safely query a nested dot-notation path on the current partial snapshot.
     * Example: `$parser->get('choices.0.message.content')`
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $data = $this->getPartial(true);
        if (!is_array($data)) {
            return $default;
        }

        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                return $default;
            }
        }

        return $current;
    }

    /**
     * Register a callback triggered whenever a chunk is fed.
     */
    public function onChunk(callable $callback): self
    {
        $this->chunkListeners[] = $callback;
        return $this;
    }

    /**
     * Register a callback triggered when a specific dot-notation path
     * receives a value or changes value during streaming.
     */
    public function onKey(string $path, callable $callback): self
    {
        $this->keyListeners[$path][] = $callback;
        return $this;
    }

    /**
     * Register a callback triggered when stream is completed.
     */
    public function onComplete(callable $callback): self
    {
        $this->completeListeners[] = $callback;
        return $this;
    }

    /**
     * Finalize the stream and return decoded JSON.
     *
     * @throws ParseException
     */
    public function finish(bool $assoc = true): mixed
    {
        $this->isFinished = true;
        $repaired = JsonRepair::repair($this->buffer);

        try {
            $result = json_decode($repaired, $assoc, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ParseException("Stream completion failed: " . $e->getMessage(), -1, $this->buffer, $e);
        }

        foreach ($this->completeListeners as $listener) {
            $listener($result, $this);
        }

        return $result;
    }

    /**
     * Get the raw buffered string.
     */
    public function getRawBuffer(): string
    {
        return $this->buffer;
    }

    /**
     * Reset internal buffer and state.
     */
    public function reset(): self
    {
        $this->buffer = '';
        $this->lastPartialSnapshot = null;
        $this->knownValues = [];
        $this->isFinished = false;
        return $this;
    }

    /**
     * Internal: evaluates registered key listeners against current partial values.
     */
    private function evaluateKeyListeners(): void
    {
        $partial = $this->getPartial(true);
        if (!is_array($partial)) {
            return;
        }

        foreach ($this->keyListeners as $path => $listeners) {
            $value = $this->extractPathValue($partial, $path);
            if ($value !== null) {
                $previous = $this->knownValues[$path] ?? null;
                if ($previous !== $value) {
                    $this->knownValues[$path] = $value;
                    foreach ($listeners as $listener) {
                        $listener($value, $path, $this);
                    }
                }
            }
        }
    }

    private function extractPathValue(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                return null;
            }
        }

        return $current;
    }
}
