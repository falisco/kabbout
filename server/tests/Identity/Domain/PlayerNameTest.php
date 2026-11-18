<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Domain;

use Kabbout\Identity\Domain\PlayerName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlayerNameTest extends TestCase
{
    public function testAGuestsNameIsTheLetterCAndSevenDigitsWhateverChanceDraws(): void
    {
        $names = [];
        foreach ([0, 7, 4821973, 9_999_999] as $drawn) {
            $names[] = PlayerName::guest(new ScriptedChance([$drawn]))->toString();
        }

        self::assertSame(['C0000000', 'C0000007', 'C4821973', 'C9999999'], $names);
    }

    public function testChanceThatLeavesItsBoundsGivesNoName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PlayerName::guest(new ScriptedChance([10_000_000]));
    }

    /** @return iterable<string, array{string}> */
    public static function whatIsNoName(): iterable
    {
        yield 'nothing' => [''];
        yield 'a small c' => ['c4821973'];
        yield 'six digits' => ['C482197'];
        yield 'eight digits' => ['C48219734'];
        yield 'a letter among the digits' => ['C482197a'];
        yield 'a space before' => [' C4821973'];
        yield 'a new line after' => ["C4821973\n"];
        yield 'digits of another script' => ['C٤٨٢١٩٧٣'];
        yield 'markup' => ['<b>C4821973</b>'];
    }

    #[DataProvider('whatIsNoName')]
    public function testWhatIsNotTheLetterCAndSevenDigitsIsNoName(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PlayerName::fromString($name);
    }

    public function testANameReadFromWhereItWasKeptIsTheSameName(): void
    {
        self::assertSame('C4821973', PlayerName::fromString('C4821973')->toString());
    }
}
