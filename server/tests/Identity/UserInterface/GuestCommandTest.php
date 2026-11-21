<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\UserInterface;

use Kabbout\Identity\Application\EnterAsGuest;
use Kabbout\Identity\Application\RecogniseGuest;
use Kabbout\Identity\UserInterface\Console\GuestCommand;
use Kabbout\Tests\Identity\Application\CountingEntries;
use Kabbout\Tests\Identity\Domain\ListedPlayers;
use Kabbout\Tests\Identity\Domain\ScriptedChance;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GuestCommandTest extends TestCase
{
    public function testItMakesAGuestAndPrintsTheNameAndTheSecretThatProvesIt(): void
    {
        $players = new ListedPlayers();
        $output = new BufferedOutput();

        $status = (new GuestCommand(new EnterAsGuest($players, new ScriptedChance([42]), new CountingEntries())))(new SymfonyStyle(new ArrayInput([]), $output));

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame(1, preg_match('/^name: (C0000042)\nsecret: ([0-9a-f]{64})\n$/D', $output->fetch(), $printed));
        self::assertSame($printed[1], (new RecogniseGuest($players))($printed[2])?->name->toString());
    }

    public function testWhenNoGuestCanComeInItSaysSoAndFails(): void
    {
        $output = new BufferedOutput();

        $status = (new GuestCommand(new EnterAsGuest(new ListedPlayers(), new ScriptedChance([42]), new CountingEntries(0))))(new SymfonyStyle(new ArrayInput([]), $output));

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('No guest was made', $output->fetch());
    }
}
