<?php

declare(strict_types=1);

namespace JsonFlux\Exceptions;

/**
 * Thrown when JSON or SSE parsing fails unrecoverably.
 */
class ParseException extends JsonFluxException
{
    public function __construct(
        string $message,
        private readonly int $position = -1,
        private readonly string $snippet = '',
        ?\Throwable $previous = null
    ) {
        $formatted = $position >= 0 
            ? sprintf('%s at position %d: "%s"', $message, $position, $snippet) 
            : $message;
        parent::__construct($formatted, 0, $previous);
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }
}
