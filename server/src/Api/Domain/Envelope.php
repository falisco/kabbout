<?php

declare(strict_types=1);

namespace Kabbout\Api\Domain;

/**
 * The shape of everything the API says. An answer carries the version of the API; a refusal is
 * `{ "error": { "code", "message" } }`, the shape the bridge uses, with whatever else helps the client.
 * Every door builds what it says here, so that a client reads one API and not one per route.
 */
final readonly class Envelope
{
    public const int VERSION = 1;

    /**
     * @param array<string, mixed> $said
     *
     * @return array<string, mixed>
     */
    public static function answer(array $said): array
    {
        return ['version' => self::VERSION, ...$said];
    }

    /**
     * @param string               $code    one word a client can act on
     * @param string               $message a sentence for whoever writes the client
     * @param array<string, mixed> $more
     *
     * @return array{error: array<string, mixed>}
     */
    public static function error(string $code, string $message, array $more = []): array
    {
        return ['error' => ['code' => $code, 'message' => $message, ...$more]];
    }
}
