<?php

declare(strict_types=1);

namespace Kabbout\Infrastructure\Engine\Bridge\Mapping;

use Kabbout\Domain\Engine\Exception\EngineUnavailable;

/**
 * Reads what the bridge answered. Anything that does not have the expected shape is a failure of the
 * bridge or of this adapter, reported as EngineUnavailable rather than passed on half-read.
 */
final class Json
{
    /** @return array<string, mixed> */
    public static function object(mixed $value, string $what): array
    {
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw new EngineUnavailable(\sprintf('The bridge answered no %s.', $what));
        }
        $object = [];
        foreach ($value as $key => $item) {
            $object[(string) $key] = $item;
        }

        return $object;
    }

    /** @return list<mixed> */
    public static function list(mixed $value, string $what): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw new EngineUnavailable(\sprintf('The bridge answered no list of %s.', $what));
        }

        return $value;
    }

    /** @return list<array<string, mixed>> */
    public static function objects(mixed $value, string $what): array
    {
        return array_map(static fn (mixed $item): array => self::object($item, $what), self::list($value, $what));
    }

    /** @return list<string> */
    public static function strings(mixed $value, string $what): array
    {
        return array_map(static fn (mixed $item): string => \is_string($item) ? $item : throw new EngineUnavailable(\sprintf('The bridge answered no list of %s.', $what)), self::list($value, $what));
    }

    public static function bool(mixed $value, string $what): bool
    {
        return \is_bool($value) ? $value : throw new EngineUnavailable(\sprintf('The bridge answered no %s.', $what));
    }

    public static function int(mixed $value, string $what): int
    {
        return \is_int($value) ? $value : throw new EngineUnavailable(\sprintf('The bridge answered no %s.', $what));
    }
}
