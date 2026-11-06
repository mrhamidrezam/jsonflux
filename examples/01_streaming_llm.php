<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/StreamParser.php';
require_once __DIR__ . '/../src/JsonRepair.php';
require_once __DIR__ . '/../src/JsonFlux.php';

use JsonFlux\JsonFlux;

echo "--- 01: Streaming LLM Token Consumption ---\n\n";

$stream = JsonFlux::stream();

// Simulated streaming chunks coming over HTTP chunked transfer
$simulatedChunks = [
    '{"id": "chatcmpl-99", "model": "gemini-2.5-flash", ',
    '"choices": [{"index": 0, "delta": {"role": "assistant", ',
    '"content": "Here is your requested ',
    'recipe analysis for Persian Saffron Rice.", ',
    '"tags": ["persian", "cooking", "rice"]',
    '}, "finish_reason": null}]}',
];

$stream->onKey('choices.0.delta.content', function ($content) {
    echo "[Content Stream Update]: " . $content . "\n";
});

foreach ($simulatedChunks as $i => $chunk) {
    echo "Feeding chunk #" . ($i + 1) . "... ";
    $stream->feed($chunk);
    
    // Check partial snapshot mid-stream without crashing
    $partialRole = $stream->get('choices.0.delta.role', 'pending...');
    echo "(Current role detected: {$partialRole})\n";
}

echo "\nFinalizing stream...\n";
$result = $stream->finish();
echo "Stream finalized successfully! Model: " . $result['model'] . "\n";
echo "Total tags: " . implode(', ', $result['choices'][0]['delta']['tags']) . "\n";
