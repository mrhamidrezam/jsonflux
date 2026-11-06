<?php

declare(strict_types=1);

use JsonFlux\SSEParser;

class SSEParserTest
{
    public function testParsesBasicSseEvents(): void
    {
        $sse = new SSEParser();
        $payload = "event: message\ndata: {\"text\": \"Hello\"}\n\nevent: message\ndata: {\"text\": \"World\"}\n\n";

        $events = $sse->feed($payload);
        Assert::same(2, count($events));
        Assert::same('{"text": "Hello"}', $events[0]['data']);
        Assert::same('{"text": "World"}', $events[1]['data']);
    }

    public function testHandlesFragmentedPackets(): void
    {
        $sse = new SSEParser();

        // Feed first half of an SSE event
        $e1 = $sse->feed("data: {\"progress\": 5");
        Assert::same(0, count($e1)); // Incomplete, should not fire

        // Feed the second half and boundary
        $e2 = $sse->feed("0}\n\n");
        Assert::same(1, count($e2));
        Assert::same('{"progress": 50}', $e2[0]['data']);
    }

    public function testDetectsStreamDoneMarker(): void
    {
        $sse = new SSEParser();
        Assert::false($sse->isDone());

        $sse->feed("data: [DONE]\n\n");
        Assert::true($sse->isDone());
    }

    public function testExtractJsonGenerator(): void
    {
        $sse = new SSEParser();
        $stream = [
            "data: {\"token\": \"A\"}\n\n",
            "data: {\"token\": \"B\"}\n\n",
            ": keep-alive comment\n\n",
            "data: [DONE]\n\n",
        ];

        $tokens = [];
        foreach ($sse->extractJson($stream) as $json) {
            $tokens[] = $json['token'];
        }

        Assert::same(['A', 'B'], $tokens);
    }
}
