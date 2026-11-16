<?php

declare(strict_types=1);

namespace Kabbout\Tests\Support;

use Kabbout\Game\Infrastructure\Bridge\Mapping\Json;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Anthropic's Messages API for tests, behind the HTTP client the bundle's platform is given in the test
 * environment (config/packages/ai.yaml). It answers what it was scripted to answer, in the API's own
 * format, and keeps every request: the tests run the real bundle, the real bridge and the real agent,
 * down to the bytes that would leave the server.
 */
final class AnthropicMock
{
    /** @var list<array<string, mixed>|int> the bodies to answer, in order; a number is an error status */
    private static array $script = [];
    /** @var list<array{url: string, headers: list<string>, body: array<string, mixed>}> */
    private static array $requests = [];

    /** @param array<string, mixed>|int ...$answers */
    public static function willAnswer(array|int ...$answers): void
    {
        self::$script = array_values($answers);
        self::$requests = [];
    }

    /** @return list<array{url: string, headers: list<string>, body: array<string, mixed>}> */
    public static function requests(): array
    {
        return self::$requests;
    }

    /** @return array<string, mixed> the model says something, and stops */
    public static function text(string $text): array
    {
        return self::message([['type' => 'text', 'text' => $text]], 'end_turn');
    }

    /**
     * the model calls tools.
     *
     * @param array{string, array<string, mixed>} ...$calls each a tool name and its arguments
     *
     * @return array<string, mixed>
     */
    public static function toolUse(array ...$calls): array
    {
        $blocks = [];
        foreach (array_values($calls) as $i => [$name, $input]) {
            $blocks[] = ['type' => 'tool_use', 'id' => 'toolu_'.$i, 'name' => $name, 'input' => [] === $input ? new \stdClass() : $input];
        }

        return self::message($blocks, 'tool_use');
    }

    /** @param array<string, mixed> $options */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        $body = json_decode(\is_string($options['body'] ?? null) ? $options['body'] : '{}', true, flags: \JSON_THROW_ON_ERROR);
        $headers = array_values(array_filter(\is_array($options['headers'] ?? null) ? $options['headers'] : [], \is_string(...)));
        self::$requests[] = ['url' => $method.' '.$url, 'headers' => $headers, 'body' => Json::object($body, 'request')];
        $next = array_shift(self::$script) ?? throw new \LogicException('The platform was asked more often than scripted.');
        if (\is_int($next)) {
            return new MockResponse('{"type":"error","error":{"type":"authentication_error","message":"invalid x-api-key"}}', ['http_code' => $next, 'response_headers' => ['content-type' => 'application/json']]);
        }

        return new MockResponse(json_encode($next, \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }

    /**
     * @param list<array<string, mixed>> $content
     *
     * @return array<string, mixed>
     */
    private static function message(array $content, string $stopReason): array
    {
        return ['id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5', 'content' => $content, 'stop_reason' => $stopReason, 'stop_sequence' => null, 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]];
    }
}
