<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Application;

use Kabbout\Identity\Application\EnterAsGuest;
use Kabbout\Identity\Application\RecogniseVisitor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecogniseVisitorTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function whatIsNobodysSecret(): iterable
    {
        yield 'a secret nobody was given' => [str_repeat('ab', 32)];
        yield 'nothing' => [''];
        yield 'too short to be one' => [str_repeat('ab', 31)];
        yield 'too long to be one' => [str_repeat('ab', 33)];
        yield 'not hexadecimal' => [str_repeat('zz', 32)];
        yield 'in capitals' => [str_repeat('AB', 32)];
        yield 'followed by a new line' => [str_repeat('ab', 32)."\n"];
    }

    #[DataProvider('whatIsNobodysSecret')]
    public function testWhatIsNobodysSecretIsNobody(string $presented): void
    {
        $players = new ListedPlayers();
        (new EnterAsGuest($players, new ScriptedChance([1])))();

        self::assertNull((new RecogniseVisitor($players))($presented));
    }

    public function testASecretAlteredByOneDigitIsNobody(): void
    {
        $players = new ListedPlayers();
        $secret = (new EnterAsGuest($players, new ScriptedChance([1])))()->secret->reveal();
        $altered = ('0' === $secret[0] ? '1' : '0').substr($secret, 1);

        self::assertNull((new RecogniseVisitor($players))($altered));
        self::assertNotNull((new RecogniseVisitor($players))($secret));
    }
}
