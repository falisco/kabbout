<?php

declare(strict_types=1);

namespace Kabbout\Assistant\UserInterface\Console;

use Kabbout\Assistant\Application\Conventions\ConventionsModelFailed;
use Kabbout\Assistant\Application\Conventions\InterpretConventions;
use Kabbout\Assistant\Application\Conventions\NotUnderstood;
use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\Lang;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Measures the reading of conventions: each sentence of a set has the card it should give, or none when
 * it describes no card, and every one is read as a player's would be. A sentence counts when the card
 * that comes back is that card, setting for setting, or when none comes back and none was expected.
 * The rate is given overall and for each way of writing (French, English, Arabic, derja in Latin letters).
 */
#[AsCommand(name: 'kabbout:conventions:eval', description: 'Reads the sentences of a set, each with the card it should give, and prints how often the card is the expected one.')]
final readonly class EvalConventionsCommand
{
    private const string NONE = 'not understood';

    public function __construct(private InterpretConventions $interpret, private AiConfiguration $configuration)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('The set, a JSON file (eval/conventions/sentences.json).')] string $file): int
    {
        $settings = $this->configuration->settings();
        if (null === $settings) {
            $io->getErrorStyle()->writeln('The AI is off: set ANTHROPIC_API_KEY to measure the reading of conventions.');

            return Command::FAILURE;
        }
        $set = self::set($file);
        $io->writeln(\sprintf('Conventions evaluation: %d sentences, %s on %s, %s.', \count($set), $settings->model, $settings->platform->value, date('Y-m-d')));

        $right = [];
        $unread = 0;
        foreach ($set as $number => [$writing, $lang, $sentence, $expected]) {
            try {
                $got = ($this->interpret)($sentence, $lang)->card;
            } catch (NotUnderstood) {
                $got = null;
            } catch (ConventionsModelFailed) {
                $got = false;
                ++$unread;
            }
            $hit = false !== $got && (null === $expected ? null === $got : null !== $got && $got->equals($expected));
            $right[$writing][] = $hit;
            $io->writeln(\sprintf('  %02d  %-5s  %s  %s', $number + 1, $writing, $hit ? 'right' : (false === $got ? 'no answer from the model' : 'wrong'), $sentence));
            if (!$hit && false !== $got) {
                $io->writeln(\sprintf('      expected %s, got %s', self::told($expected, $got), self::told($got, $expected)));
            }
        }

        $total = array_sum(array_map(array_sum(...), $right));
        $byWriting = implode(', ', array_map(static fn (string $writing, array $hits): string => \sprintf('%s %d/%d', $writing, array_sum($hits), \count($hits)), array_keys($right), $right));
        $io->writeln(\sprintf('Expected card: %d/%d (%s). Sentences the model gave no answer to: %d.', $total, \count($set), $byWriting, $unread));

        return Command::SUCCESS;
    }

    /** a card as its settings that are not the classic card's, so that two cards are told apart at a glance */
    private static function told(?ConventionCard $card, ?ConventionCard $beside): string
    {
        if (null === $card) {
            return self::NONE;
        }
        // the settings either card has that are not the classic one's, so that both are shown on the same settings
        $classic = ConventionCard::classic()->toArray();
        $told = array_diff_assoc($card->toArray(), $classic) + array_diff_assoc($beside?->toArray() ?? [], $classic);
        $shown = array_intersect_key($card->toArray(), $told);

        return [] === $shown ? 'the classic card' : (string) json_encode($shown);
    }

    /**
     * @return list<array{string, Lang, string, ?ConventionCard}> each sentence with its way of writing, the language of the screen and the card expected
     *
     * @throws \InvalidArgumentException for a file that is not a set
     */
    private static function set(string $file): array
    {
        $sentences = is_file($file) ? json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR) : null;
        if (!\is_array($sentences) || [] === $sentences) {
            throw new \InvalidArgumentException($file.' is not a set of sentences.');
        }
        $set = [];
        foreach ($sentences as $one) {
            if (!\is_array($one) || !\is_string($one['writing'] ?? null) || !\is_string($one['sentence'] ?? null) || !\array_key_exists('expected', $one) || !(\is_array($one['expected']) || null === $one['expected'])) {
                throw new \InvalidArgumentException($file.' holds something that is not a sentence of the set.');
            }
            $lang = Lang::tryFrom(\is_string($one['lang'] ?? null) ? $one['lang'] : '') ?? throw new \InvalidArgumentException($file.' holds a sentence with no language of the screen.');
            $set[] = [$one['writing'], $lang, $one['sentence'], null === $one['expected'] ? null : ConventionCard::fromArray($one['expected'])];
        }

        return $set;
    }
}
