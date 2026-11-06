<?php

declare(strict_types=1);

use JsonFlux\JsonRepair;
use JsonFlux\Exceptions\ParseException;

class JsonRepairTest
{
    public function testValidJsonUntouched(): void
    {
        $valid = '{"name":"Alice","age":28,"active":true,"tags":["php","ai"]}';
        Assert::same($valid, JsonRepair::repair($valid));
        Assert::true(JsonRepair::isValidJson($valid));
    }

    public function testClosesUnclosedString(): void
    {
        $truncated = '{"message": "Hello worl';
        $repaired = JsonRepair::repair($truncated);
        Assert::true(JsonRepair::isValidJson($repaired));
        $data = json_decode($repaired, true);
        Assert::same('Hello worl', $data['message']);
    }

    public function testClosesNestedArraysAndObjects(): void
    {
        $truncated = '{"users": [{"id": 1, "profile": {"name": "Bob", "roles": ["admin"';
        $repaired = JsonRepair::repair($truncated);
        Assert::true(JsonRepair::isValidJson($repaired));
        $data = json_decode($repaired, true);
        Assert::same('Bob', $data['users'][0]['profile']['name']);
        Assert::same(['admin'], $data['users'][0]['profile']['roles']);
    }

    public function testHandlesTrailingCommas(): void
    {
        $input = '{"a": 1, "b": [2, 3, ], }';
        $repaired = JsonRepair::repair($input);
        Assert::true(JsonRepair::isValidJson($repaired));
        $data = json_decode($repaired, true);
        Assert::same(1, $data['a']);
        Assert::same([2, 3], $data['b']);
    }

    public function testHandlesDanglingColon(): void
    {
        $input = '{"title": "Post", "author":';
        $repaired = JsonRepair::repair($input);
        Assert::true(JsonRepair::isValidJson($repaired));
        $data = json_decode($repaired, true);
        Assert::same('Post', $data['title']);
        Assert::same(null, $data['author']);
    }

    public function testNormalizesSingleQuotes(): void
    {
        $input = "{'type': 'model', 'ready': true, 'name': 'Gemini'}";
        $repaired = JsonRepair::repair($input);
        Assert::true(JsonRepair::isValidJson($repaired));
        $data = json_decode($repaired, true);
        Assert::same('model', $data['type']);
        Assert::same('Gemini', $data['name']);
    }

    public function testNormalizesPythonLiterals(): void
    {
        $input = '{"enabled": True, "archived": False, "metadata": None}';
        $repaired = JsonRepair::repair($input);
        Assert::true(JsonRepair::isValidJson($repaired));
        $data = json_decode($repaired, true);
        Assert::same(true, $data['enabled']);
        Assert::same(false, $data['archived']);
        Assert::same(null, $data['metadata']);
    }

    public function testNormalizesUnquotedKeys(): void
    {
        $input = '{status: "ok", count: 42, is_live: true}';
        $repaired = JsonRepair::repair($input);
        Assert::true(JsonRepair::isValidJson($repaired));
        $data = json_decode($repaired, true);
        Assert::same('ok', $data['status']);
        Assert::same(42, $data['count']);
        Assert::same(true, $data['is_live']);
    }

    public function testStripsComments(): void
    {
        $input = <<<JSON
        {
            // This is a single line comment
            "key": "value",
            /* Multi-line
               comment block */
            "num": 100
        }
        JSON;
        $repaired = JsonRepair::repair($input);
        Assert::true(JsonRepair::isValidJson($repaired));
        $data = json_decode($repaired, true);
        Assert::same('value', $data['key']);
        Assert::same(100, $data['num']);
    }

    public function testStripsMarkdownFences(): void
    {
        $input = "```json\n{\"fenced\": true, \"version\": 2}\n```";
        $repaired = JsonRepair::repair($input);
        Assert::true(JsonRepair::isValidJson($repaired));
        $data = json_decode($repaired, true);
        Assert::same(true, $data['fenced']);
        Assert::same(2, $data['version']);
    }

    public function testEmptyStringReturnsEmptyObject(): void
    {
        $repaired = JsonRepair::repair('   ');
        Assert::same('{}', $repaired);
    }
}
