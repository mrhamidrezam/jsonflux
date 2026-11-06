<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/JsonRepair.php';
require_once __DIR__ . '/../src/JsonFlux.php';

use JsonFlux\JsonFlux;

echo "--- 02: Resilient JSON Repair ---\n\n";

$brokenSamples = [
    'Unclosed string & array' => '{"title": "Deep Learning Guide", "chapters": ["Intro", "CNNs", "Transfo',
    'Unquoted keys & Python booleans' => '{status: "active", verified: True, count: 42, notes: None}',
    'Trailing commas & dangling colon' => '{"items": [1, 2, 3, ], "meta": {"debug": false, }, "extra":',
    'Single quotes & comments' => "{// user profile\n'name': 'Hamidreza', 'skills': ['PHP', 'Python']}",
];

foreach ($brokenSamples as $label => $broken) {
    echo ">> Case: {$label}\n";
    echo "Input:    " . str_replace("\n", " ", $broken) . "\n";
    $repaired = JsonFlux::repair($broken);
    echo "Repaired: " . $repaired . "\n";
    $decoded = JsonFlux::decode($broken);
    echo "Decoded:  " . json_encode($decoded) . "\n\n";
}
