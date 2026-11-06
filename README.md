# ⚡ JsonFlux

[![Tests](https://github.com/mrhamidrezam/jsonflux/actions/workflows/tests.yml/badge.svg)](https://github.com/mrhamidrezam/jsonflux/actions)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Zero Dependencies](https://img.shields.io/badge/dependencies-0-success.svg)](composer.json)
[![Code Style](https://img.shields.io/badge/code%20style-PSR--4%20%26%20Strict-blue)](composer.json)

**JsonFlux** is a high-performance, zero-dependency PHP 8.2+ streaming JSON parser, partial chunk reader, and resilient JSON repair engine designed specifically for modern LLM Server-Sent Events (SSE) and fragmented JSON streams (OpenAI, Gemini, Anthropic Claude, Ollama, DeepSeek).

---

## 🎯 The Problem

Large Language Models (LLMs) output text token-by-token. When generating structured data:
1. **Mid-stream inspection fails**: Standard `json_decode()` crashes with syntax errors on incomplete buffers (`{"title": "Deep Lea` throws `JSON_ERROR_SYNTAX`).
2. **Context limits cause truncation**: Truncated responses leave unclosed quotes, brackets, or dangling colons.
3. **Conversational wrappers**: Models often wrap valid JSON in conversational prose (`Here is the JSON: ```json ... ``` Enjoy!`) or custom XML tags (`<tool_call>...</tool_call>`).
4. **LLM formatting quirks**: Models output Python literals (`True`, `False`, `None`), trailing commas, unquoted keys, or single quotes.

**JsonFlux solves all of these without external dependencies.**

---

## 🏗️ Architecture

```
                                      ┌────────────────┐
                                      │ LLM SSE Stream │
                                      └───────┬────────┘
                                              │ (text/event-stream)
                                              ▼
                                      ┌────────────────┐
                                      │   SSEParser    │
                                      └───────┬────────┘
                                              │ (data frames)
                                              ▼
  ┌───────────────────┐               ┌────────────────┐
  │ Fragmented/Broken ├──────────────►│   JsonRepair   │
  │   JSON String     │               └───────┬────────┘
  └───────────────────┘                       │
                                              ▼
  ┌───────────────────┐               ┌────────────────┐
  │ Markdown / Prose  ├──────────────►│   Extractor    │
  │ With Codeblocks   │               └───────┬────────┘
  └───────────────────┘                       │
                                              ▼
                                      ┌────────────────┐
                                      │  StreamParser  │◄──── onKey('status', ...)
                                      └───────┬────────┘
                                              │
                                              ▼
                                      ┌────────────────┐
                                      │ Valid PHP Data │
                                      │ Array / Object │
                                      └────────────────┘
```

---

## ✨ Features

- 🛡️ **Resilient JSON Repair**: Automatically closes unclosed quotes, brackets `]`, and braces `}`. Fixes dangling colons, trailing commas, Python literals (`True`/`False`/`None`), unquoted keys, and strips comments.
- 🌊 **Progressive Stream Parser**: Safely query partial JSON objects mid-stream (`$stream->get('user.profile.name')`) while tokens are still arriving.
- 🔔 **Reactive Event Hooks**: Register callbacks on token arrivals (`onChunk`), dot-notation key updates (`onKey`), or stream completion (`onComplete`).
- 🔍 **Markdown & Tag Extractor**: Extract JSON out of markdown fences (````json ... ````) or custom XML tags (`<tool_call>...</tool_call>`) mixed in conversational text.
- 📡 **Server-Sent Events (SSE) Engine**: Reassembles fragmented TCP packets into standardized SSE messages and decodes `data:` payloads seamlessly.
- 💻 **Standalone CLI Runner**: Test, repair, and extract JSON directly from your terminal or shell pipeline (`bin/jsonflux`).
- 🚀 **Zero External Dependencies**: 100% pure modern PHP 8.2+ with strict typing and PSR-4 autoloading.

---

## 📦 Installation

Install via Composer:

```bash
composer require mrhamidrezam/jsonflux
```

Or clone directly (zero external dependencies required):

```bash
git clone https://github.com/mrhamidrezam/jsonflux.git
```

---

## 🚀 Quick Start

### 1. Resilient Repair & Safe Decode

```php
use JsonFlux\JsonFlux;

// Broken, truncated JSON with unclosed arrays, single quotes, and Python literals:
$broken = "{'name': 'Hamidreza', 'tags': ['ai', 'php', 'llm', active: True, 'score':";

// Safely repair into valid standard JSON:
$cleanJson = JsonFlux::repair($broken);
// Output: {"name": "Hamidreza", "tags": ["ai", "php", "llm"], "active": true, "score": null}

// Or decode directly into an array in one step:
$data = JsonFlux::decode($broken);
echo $data['name']; // "Hamidreza"
echo $data['tags'][0]; // "ai"
```

---

### 2. Progressive Streaming LLM Parser

```php
use JsonFlux\JsonFlux;

$stream = JsonFlux::stream();

// Listen to specific keys as they form in real time:
$stream->onKey('choices.0.delta.content', function ($content) {
    echo "[Streaming delta]: " . $content . "\n";
});

// Feed incoming network chunks (curl, reactphp, swoole, or native stream):
$stream->feed('{"choices": [{"delta": {"role": "ass');
$stream->feed('istant", "content": "Hello wor');
$stream->feed('ld!"}}]}');

// Access intermediate state safely mid-stream:
$role = $stream->get('choices.0.delta.role'); // "assistant"
$content = $stream->get('choices.0.delta.content'); // "Hello world!"

// Finalize when done:
$result = $stream->finish();
```

---

### 3. Extracting JSON from Markdown & XML Tags

```php
use JsonFlux\JsonFlux;
use JsonFlux\Extractor;

$response = <<<TEXT
Sure, here is your function call:
```json
{
    "action": "book_flight",
    "destination": "Shiraz",
    "passengers": 2
}
```
Please let me know if you want to proceed.
TEXT;

// Automatically locates, cleans, and decodes the JSON payload:
$payload = JsonFlux::extract($response);
echo $payload['destination']; // "Shiraz"

// Or extract tool calls enclosed in XML tags:
$xmlText = '<tool_call>{"query": "weather in Tehran"}</tool_call>';
$tools = Extractor::extractByTag($xmlText, 'tool_call');
echo $tools[0]['query']; // "weather in Tehran"
```

---

### 4. Server-Sent Events (SSE) Processing

```php
use JsonFlux\JsonFlux;

$sse = JsonFlux::sse();

$chunks = [
    "event: message\ndata: {\"token\": \"Deep\"}\n\n",
    "event: message\ndata: {\"token\": \"Seek\"}\n\n",
    "data: [DONE]\n\n"
];

foreach ($sse->extractJson($chunks) as $data) {
    echo $data['token'] . " "; // Prints "Deep Seek "
}

if ($sse->isDone()) {
    echo "Stream finished successfully.\n";
}
```

---

## 🖥️ Command Line Interface (CLI)

JsonFlux includes a powerful standalone CLI utility:

```bash
# Repair truncated or malformed JSON
php bin/jsonflux repair '{"title": "LLM Guide", "tags": ["php", "ai"'

# Extract JSON from markdown
php bin/jsonflux extract "The result is: ```json {\"status\": \"ok\"} ```"

# Validate JSON strictly
php bin/jsonflux validate '{"valid": true}'

# Pipe Server-Sent Events
curl -s -N https://api.openai.com/v1/chat/completions ... | php bin/jsonflux sse
```

---

## 🧪 Testing

Run the zero-dependency test suite:

```bash
php tests/run.php
```

All 28 tests across 5 suites run in under **5 milliseconds**:
```
=== JsonFlux Test Suite ===
Found 5 test suites.

► Running ExtractorTest        ✔
► Running JsonFluxFacadeTest   ✔
► Running JsonRepairTest       ✔
► Running SSEParserTest        ✔
► Running StreamParserTest     ✔

ALL TESTS PASSED! (28 tests, 73 assertions in 4.51ms)
```

---

## 📋 API Summary

| Class / Method | Description |
|---|---|
| `JsonFlux::repair(string $json): string` | Repairs truncated or malformed JSON into standard JSON. |
| `JsonFlux::decode(string $json, bool $assoc = true): mixed` | Resiliently decodes JSON into PHP arrays or objects. |
| `JsonFlux::encode(mixed $val): string` | Encodes clean JSON with unescaped unicode & slashes. |
| `JsonFlux::isValid(string $json): bool` | Returns `true` if input is valid JSON without modification. |
| `JsonFlux::extract(string $text): mixed` | Extracts and parses the first JSON payload found in markdown or text. |
| `JsonFlux::extractAll(string $text): array` | Extracts all JSON objects found in markdown, tags, or text. |
| `JsonFlux::stream(): StreamParser` | Creates a new progressive streaming chunk parser. |
| `JsonFlux::sse(): SSEParser` | Creates a new Server-Sent Events stream processor. |
| `StreamParser::feed(string $chunk)` | Ingests a new text/token chunk. |
| `StreamParser::get(string $path)` | Queries a nested dot-notation path on the partial document. |
| `StreamParser::onKey(string $path, callable $fn)` | Reactive callback triggered when path value updates. |

---

## 📄 License

This project is open-sourced under the [MIT License](LICENSE).

Developed with ❤️ by [Hamidreza](https://github.com/mrhamidrezam).
