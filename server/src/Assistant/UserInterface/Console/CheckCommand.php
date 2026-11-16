<?php

declare(strict_types=1);

namespace Kabbout\Assistant\UserInterface\Console;

use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Assistant\Domain\InvalidAiSettings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Run before the server takes its first request: settings that cannot work stop the start, with one line that says why. */
#[AsCommand(name: 'kabbout:check', description: 'Checks the settings this server was started with.')]
final readonly class CheckCommand
{
    public function __construct(private AiConfiguration $configuration)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $settings = $this->configuration->settings();
        } catch (InvalidAiSettings $e) {
            $io->getErrorStyle()->writeln('Kabbout cannot start: '.$e->getMessage());

            return Command::FAILURE;
        }
        $io->writeln(null === $settings
            ? 'AI: off. The game is complete without it.'
            : \sprintf('AI: on, %s with %s, run by %s.', $settings->platform->value, $settings->model, $settings->provider ?? 'the provider the platform picks'));

        return Command::SUCCESS;
    }
}
