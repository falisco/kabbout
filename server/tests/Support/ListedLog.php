<?php

declare(strict_types=1);

namespace Kabbout\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * A log for tests: every line the server would write, kept in a list. A line is the message and all
 * that came with it, written out as a logger would: what is text as text, an error with its message
 * and its trace, anything else by the name of its class.
 */
final class ListedLog extends AbstractLogger
{
    /** @var list<string> */
    public array $lines = [];

    /** @param array<array-key, mixed> $context */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->lines[] = \sprintf('[%s] %s %s', self::written($level), $message, self::written($context));
    }

    private static function written(mixed $value): string
    {
        return match (true) {
            \is_array($value) => '{'.implode(', ', array_map(static fn (int|string $key): string => $key.': '.self::written($value[$key]), array_keys($value))).'}',
            \is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => get_debug_type($value),
        };
    }
}
