<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SSEParser.php';
require_once __DIR__ . '/../src/JsonRepair.php';
require_once __DIR__ . '/../src/JsonFlux.php';

use JsonFlux\JsonFlux;

echo "--- 04: Server-Sent Events (SSE) Stream Processing ---\n\n";

$sseStream = [
    ": connected\n\n",
    "event: token\ndata: {\"text\": \"The \"}\n\n",
    "event: token\ndata: {\"text\": \"quick \"}\n\n",
    "event: token\ndata: {\"text\": \"brown \"}\n\n",
    "event: token\ndata: {\"text\": \"fox.\"} \n\n",
    "event: end\ndata: [DONE]\n\n",
];

$sse = JsonFlux::sse();

$fullText = '';
foreach ($sse->extractJson($sseStream) as $payload) {
    if (isset($payload['text'])) {
        $fullText .= $payload['text'];
        echo "Received token: '{$payload['text']}'\n";
    }
}

echo "\nStream Complete! Assembled text: \"{$fullText}\"\n";
