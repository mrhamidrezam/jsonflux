<?php

declare(strict_types=1);

use JsonFlux\Extractor;
use JsonFlux\Exceptions\ParseException;

class ExtractorTest
{
    public function testExtractsMarkdownFencedJson(): void
    {
        $text = <<<TEXT
        Here is the model reasoning and breakdown:
        ```json
        {
            "sentiment": "positive",
            "score": 0.98,
            "entities": ["PHP", "LLM"]
        }
        ```
        Hope that helps!
        TEXT;

        $extracted = Extractor::extractFirst($text);
        Assert::same('positive', $extracted['sentiment']);
        Assert::same(0.98, $extracted['score']);
        Assert::same(['PHP', 'LLM'], $extracted['entities']);
    }

    public function testExtractsXmlTagWrappedJson(): void
    {
        $text = <<<TEXT
        I will invoke the function tool:
        <tool_call>
        {
            "name": "search_database",
            "arguments": {"query": "latency metrics", "limit": 10}
        }
        </tool_call>
        Done.
        TEXT;

        $results = Extractor::extractByTag($text, 'tool_call');
        Assert::same(1, count($results));
        Assert::same('search_database', $results[0]['name']);
        Assert::same(10, $results[0]['arguments']['limit']);
    }

    public function testExtractsRawJsonInUnstructuredProse(): void
    {
        $text = 'The server responded with {"code": 200, "message": "OK"} after processing.';
        $extracted = Extractor::extractFirst($text);
        Assert::same(200, $extracted['code']);
        Assert::same('OK', $extracted['message']);
    }

    public function testExtractsMultipleJsonDocuments(): void
    {
        $text = <<<TEXT
        First item:
        ```json
        {"id": 1, "type": "A"}
        ```
        Second item:
        ```json
        {"id": 2, "type": "B"}
        ```
        TEXT;

        $all = Extractor::extractAll($text);
        Assert::same(2, count($all));
        Assert::same('A', $all[0]['type']);
        Assert::same('B', $all[1]['type']);
    }

    public function testThrowsExceptionWhenNoJsonPresent(): void
    {
        $text = 'This is just pure conversational plain text with no JSON anywhere.';
        Assert::throws(function () use ($text) {
            Extractor::extractFirst($text);
        }, ParseException::class);
    }
}
