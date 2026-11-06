<?php

declare(strict_types=1);

use JsonFlux\StreamParser;

class StreamParserTest
{
    public function testProgressiveChunkFeeding(): void
    {
        $parser = new StreamParser();
        $parser->feed('{"choices": [');
        $parser->feed('{"delta": {"role": "ass');
        $parser->feed('istant", "content": "Hello world"');
        $parser->feed('}}]}');

        Assert::true($parser->isComplete());
        $final = $parser->finish(true);
        Assert::same('assistant', $final['choices'][0]['delta']['role']);
        Assert::same('Hello world', $final['choices'][0]['delta']['content']);
    }

    public function testPartialSnapshotMidStream(): void
    {
        $parser = new StreamParser();
        $parser->feed('{"status": "in_progress", "count": 10, "items": ["apple", "ban');

        // Stream is incomplete, but getPartial can safely recover current fields
        $partial = $parser->getPartial(true);
        Assert::same('in_progress', $partial['status']);
        Assert::same(10, $partial['count']);
        Assert::same(['apple', 'ban'], $partial['items']);
    }

    public function testDotNotationPathAccess(): void
    {
        $parser = new StreamParser();
        $parser->feed('{"config": {"database": {"host": "127.0.0.1", "port": 3306');

        Assert::same('127.0.0.1', $parser->get('config.database.host'));
        Assert::same(3306, $parser->get('config.database.port'));
        Assert::same('default_db', $parser->get('config.database.name', 'default_db'));
        Assert::same(null, $parser->get('nonexistent.path'));
    }

    public function testEventListeners(): void
    {
        $parser = new StreamParser();
        $chunksReceived = [];
        $keyTriggered = null;
        $isCompleted = false;

        $parser->onChunk(function ($c) use (&$chunksReceived) {
            $chunksReceived[] = $c;
        });

        $parser->onKey('user.name', function ($val) use (&$keyTriggered) {
            $keyTriggered = $val;
        });

        $parser->onComplete(function ($result) use (&$isCompleted) {
            $isCompleted = true;
        });

        $parser->feed('{"user": {');
        $parser->feed('"name": "Hamid');
        $parser->feed('reza"}}');

        Assert::same(3, count($chunksReceived));
        Assert::same('Hamidreza', $keyTriggered);

        $parser->finish();
        Assert::true($isCompleted);
    }
}
