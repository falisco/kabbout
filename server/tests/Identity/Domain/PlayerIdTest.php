<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Domain;

use Kabbout\Identity\Domain\PlayerId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlayerIdTest extends TestCase
{
    public function testAPlayerIdIs128RandomBitsAndIsReadBackAsItWasKept(): void
    {
        $id = PlayerId::random();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $id->toString());
        self::assertSame($id->toString(), PlayerId::fromString($id->toString())->toString());
        self::assertNotSame($id->toString(), PlayerId::random()->toString());
    }

    /** @return iterable<string, array{string}> */
    public static function whatIsNoPlayerId(): iterable
    {
        yield 'nothing' => [''];
        yield 'not hexadecimal' => [str_repeat('g', 32)];
        yield 'too short' => [str_repeat('a', 31)];
        yield 'in capitals' => [str_repeat('A', 32)];
        yield 'a new line after' => [str_repeat('a', 32)."\n"];
    }

    #[DataProvider('whatIsNoPlayerId')]
    public function testWhatIsNot32HexadecimalDigitsIsNoPlayerId(string $said): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PlayerId::fromString($said);
    }
}
