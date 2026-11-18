<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Domain;

use Kabbout\Identity\Domain\Secret;
use PHPUnit\Framework\TestCase;

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

    public function testASecretShowsNothingWhenItIsDumpedOrWrittenAsJson(): void
    {
        $secret = Secret::random();
        ob_start();
        var_dump($secret);
        $dumped = (string) ob_get_clean();

        self::assertSame([], get_object_vars($secret));
        self::assertSame('{}', json_encode($secret, \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($secret->reveal(), $dumped.print_r($secret, true).print_r(['held' => $secret], true));
    }

    public function testASecretRefusesToBeKeptAnywhere(): void
    {
        $this->expectException(\LogicException::class);

        serialize(Secret::random());
    }
}
