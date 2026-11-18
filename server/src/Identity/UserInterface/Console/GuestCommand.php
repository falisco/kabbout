<?php

declare(strict_types=1);

namespace Kabbout\Identity\UserInterface\Console;

use Kabbout\Identity\Application\EnterAsGuest;
use Kabbout\Identity\Application\EnteredTooOften;
use Kabbout\Identity\Application\NoNameFree;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A guest for a client that cannot be handed a cookie: an MCP client over HTTP sends the secret printed
 * here in its `Authorization: Bearer` header. The secret is shown this once and kept nowhere by the server.
 */
#[AsCommand(name: 'kabbout:guest', description: 'Makes a new guest and prints its name and its secret, once.')]
final readonly class GuestCommand
{
    public function __construct(private EnterAsGuest $enter)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            // a command has no address: its guests are counted against the one address that is none
            $entered = ($this->enter)(NetworkAddress::of(null), null);
        } catch (EnteredTooOften|NoNameFree|PlayersUnavailable $refused) {
            $io->getErrorStyle()->writeln('No guest was made: '.$refused->getMessage());

            return Command::FAILURE;
        }
        $io->writeln('name: '.$entered->player->name->toString());
        $io->writeln('secret: '.$entered->secret?->reveal());

        return Command::SUCCESS;
    }
}
