<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Extractor.php';
require_once __DIR__ . '/../src/JsonRepair.php';
require_once __DIR__ . '/../src/JsonFlux.php';

use JsonFlux\JsonFlux;

echo "--- 03: Extracting JSON From LLM Markdown and Prose ---\n\n";

$rawLlmResponse = <<<TEXT
Sure! Here is the structured sentiment analysis of the provided text:

```json
{
    "sentiment": "bullish",
    "confidence": 0.94,
    "signals": ["increased volume", "breakout above resistance"],
    "target_price": 142.50
}
```

Feel free to ask if you need further indicators calculated!
TEXT;

$data = JsonFlux::extract($rawLlmResponse);

echo "Extracted Sentiment: " . $data['sentiment'] . "\n";
echo "Confidence: " . ($data['confidence'] * 100) . "%\n";
echo "Signals: " . implode(', ', $data['signals']) . "\n";
