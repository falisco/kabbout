<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Domain;

use Kabbout\Identity\Domain\PlayerId;
use Kabbout\Identity\Domain\Proof;
use Kabbout\Identity\Domain\Secret;
use PHPUnit\Framework\TestCase;

/** What is random and what is kept of it: the id of a player, their secret, and its proof. */
final class SecretTest extends TestCase
{
    public function testASecretIs256RandomBitsAndNoTwoAreTheSame(): void
    {
        [$one, $other] = [Secret::random()->reveal(), Secret::random()->reveal()];

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $one);
        self::assertNotSame($one, $other);
    }

    public function testASecretPresentedIsOneOnlyInItsOwnForm(): void
    {
        $secret = Secret::random()->reveal();

        self::assertSame($secret, Secret::tryFrom($secret)?->reveal());
        self::assertNull(Secret::tryFrom(strtoupper($secret)));
        self::assertNull(Secret::tryFrom(substr($secret, 1)));
        self::assertNull(Secret::tryFrom($secret."\n"));
    }

    public function testASecretIsWrittenNowhereByAccident(): void
    {
        $secret = Secret::random();

        self::assertSame([], get_object_vars($secret));
        self::assertSame('{}', json_encode($secret, \JSON_THROW_ON_ERROR));
    }

    public function testTheProofOfASecretIsItsHashAndTellsOneSecretFromAnother(): void
    {
        $secret = Secret::random();

        self::assertSame(hash('sha256', $secret->reveal()), Proof::of($secret)->toString());
        self::assertNotSame($secret->reveal(), Proof::of($secret)->toString());
        self::assertNotSame(Proof::of(Secret::random())->toString(), Proof::of($secret)->toString());
    }

    public function testAPlayerIdIs128RandomBitsAndIsReadBackAsItWasKept(): void
    {
        $id = PlayerId::random();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $id->toString());
        self::assertTrue($id->equals(PlayerId::fromString($id->toString())));
        self::assertFalse($id->equals(PlayerId::random()));
    }

    public function testWhatIsNot32HexadecimalDigitsIsNoPlayerId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PlayerId::fromString(str_repeat('g', 32));
    }
}
