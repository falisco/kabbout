<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\Domain;

use Kabbout\Identity\Domain\Proof;
use Kabbout\Identity\Domain\Secret;
use PHPUnit\Framework\TestCase;

final class ProofTest extends TestCase
{
    public function testTheProofOfASecretIsItsSha256(): void
    {
        $secret = Secret::random();

        self::assertSame(hash('sha256', $secret->reveal()), Proof::of($secret)->toString());
    }

    public function testAProofIsNotTheSecretAndTellsOneSecretFromAnother(): void
    {
        $secret = Secret::random();

        self::assertNotSame($secret->reveal(), Proof::of($secret)->toString());
        self::assertSame(Proof::of($secret)->toString(), Proof::of($secret)->toString());
        self::assertNotSame(Proof::of(Secret::random())->toString(), Proof::of($secret)->toString());
    }
}
