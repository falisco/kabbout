<?php

declare(strict_types=1);

namespace Kabbout\Assistant\UserInterface\Console;

use Kabbout\Assistant\Application\Coach\AnswerSource;
use Kabbout\Assistant\Application\Coach\AskCoach;
use Kabbout\Assistant\Application\Coach\CoachBrief;
use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Seat;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Measures the coach: every deal of a directory holds a known mistake (the engine's first swing), and
 * the coach is asked "what did I do wrong?" about each. An answer counts when the model wrote it and it
 * names the trick and the card of that mistake. A plain summary always names them, being built from the
 * same analysis: it is counted apart, as an answer the model did not give.
 */
#[AsCommand(name: 'kabbout:coach:eval', description: 'Asks the coach about recorded deals with a known mistake and prints how often it names it.')]
final readonly class EvalCoachCommand
{
    public function __construct(private AskCoach $askCoach, private AiConfiguration $configuration)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('The directory of the deals, one JSON file each (eval/coach).')] string $directory): int
    {
        $settings = $this->configuration->settings();
        $files = glob(rtrim($directory, '/').'/*.json');
        if (null === $settings || false === $files || [] === $files) {
            $io->getErrorStyle()->writeln(null === $settings ? 'The AI is off: set ANTHROPIC_API_KEY to measure the coach.' : 'No deal in '.$directory.'.');

            return Command::FAILURE;
        }
        sort($files);
        $io->writeln(\sprintf('Coach evaluation: %d deals, %s on %s, %s.', \count($files), $settings->model, $settings->platform->value, date('Y-m-d')));

        $named = [];
        $written = 0;
        foreach ($files as $file) {
            [$lang, $expected, $brief] = self::deal($file);
            $answer = ($this->askCoach)($brief);
            $byModel = AnswerSource::Model === $answer->source;
            $hit = $byModel && \in_array($expected['trick'], $answer->tricks, true) && \in_array($expected['played'], $answer->cards, true);
            $written += $byModel ? 1 : 0;
            $named[$lang][] = $hit;
            $io->writeln(\sprintf(
                '  %s  %s  trick %d, %s: %s',
                basename($file, '.json'),
                $lang,
                $expected['trick'],
                $expected['played'],
                $hit ? 'named' : ($byModel ? 'missed' : 'no answer from the model (plain summary shown)'),
            ));
            if ($io->isVerbose()) {
                foreach ($answer->rejected as $rejected) {
                    $io->writeln('      sent back by the guard for '.implode(', ', $rejected->ungrounded).': '.$rejected->answer);
                }
                $io->writeln('      '.$answer->text);
            }
        }

        $total = array_sum(array_map(array_sum(...), $named));
        $byLang = implode(', ', array_map(static fn (string $lang, array $hits): string => \sprintf('%s %d/%d', $lang, array_sum($hits), \count($hits)), array_keys($named), $named));
        $io->writeln(\sprintf('Named the expected mistake: %d/%d (%s). Answers written by the model: %d/%d.', $total, \count($files), $byLang, $written, \count($files)));

        return Command::SUCCESS;
    }

    /**
     * @return array{string, array{trick: int, played: string}, CoachBrief} the language, the expected mistake and the question
     *
     * @throws \InvalidArgumentException for a file that is not a deal of the set
     */
    private static function deal(string $file): array
    {
        $deal = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
        $expected = \is_array($deal) ? ($deal['expected'] ?? null) : null;
        if (!\is_array($deal) || !\is_array($deal['record'] ?? null) || !\is_array($expected) || !\is_int($expected['trick'] ?? null) || !\is_string($expected['played'] ?? null)) {
            throw new \InvalidArgumentException($file.' is not a deal of the evaluation set.');
        }
        $lang = Lang::from(\is_string($deal['lang'] ?? null) ? $deal['lang'] : '');
        $seat = Seat::from(\is_int($deal['seat'] ?? null) ? $deal['seat'] : -1);
        $question = \is_string($deal['question'] ?? null) ? $deal['question'] : '';

        return [$lang->value, ['trick' => $expected['trick'], 'played' => $expected['played']], new CoachBrief(DealRecord::fromArray($deal['record']), $seat, $lang, $question)];
    }
}
