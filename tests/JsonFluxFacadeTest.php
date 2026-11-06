<?php

declare(strict_types=1);

use JsonFlux\JsonFlux;
use JsonFlux\StreamParser;
use JsonFlux\SSEParser;

class JsonFluxFacadeTest
{
    public function testDecodeValidAndTruncated(): void
    {
        $valid = '{"status": "ok"}';
        Assert::same(['status' => 'ok'], JsonFlux::decode($valid));

        $broken = '{"status": "partial", "items": [1, 2';
        Assert::same(['status' => 'partial', 'items' => [1, 2]], JsonFlux::decode($broken));
    }

    public function testEncodeHelper(): void
    {
        $data = ['name' => 'سلام / Hello'];
        $json = JsonFlux::encode($data);
        Assert::contains('سلام', $json); // Unescaped unicode
        Assert::contains('/', $json);     // Unescaped slashes
    }

    public function testValidationHelper(): void
    {
        Assert::true(JsonFlux::isValid('{"valid": true}'));
        Assert::false(JsonFlux::isValid('{"invalid": true,'));
    }

    public function testFactoryHelpers(): void
    {
        Assert::true(JsonFlux::stream() instanceof StreamParser);
        Assert::true(JsonFlux::sse() instanceof SSEParser);
    }
}
