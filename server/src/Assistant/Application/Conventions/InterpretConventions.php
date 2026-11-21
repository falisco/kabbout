<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Conventions;

use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\Exception\InvalidConventionCard;
use Kabbout\Game\Domain\Lang;

/**
 * A player says how they and their partner bid; this turns the sentence into a conventions card.
 *
 * The model translates and nothing more. What it read becomes a card only if the domain takes it for
 * one, so nothing outside the card's bounds gets through whatever the model was told or talked into.
 * Of what it says it could not place, only pieces found in the player's own sentence are kept: no
 * sentence of the model's ever reaches the player. The card is read back to them by code, from the card.
 *
 * It counts nothing: a door that lets visitors in goes through InterpretForVisitor.
 */
final readonly class InterpretConventions
{
    public const int MAX_SENTENCE_LENGTH = 300;
    private const int MOST_UNPLACED = 6;

    public function __construct(private ConventionsModel $model)
    {
    }

    /**
     * @throws InvalidSentence        a sentence that is empty or too long
     * @throws NotUnderstood
     * @throws ConventionsModelFailed
     */
    public function __invoke(string $sentence, Lang $lang): Interpreted
    {
        $sentence = self::sentence($sentence);
        $reading = $this->model->read($sentence, $lang);
        if (!$reading->understood) {
            throw new NotUnderstood('The sentence describes no convention.');
        }
        try {
            $card = ConventionCard::fromArray($reading->settings);
        } catch (InvalidConventionCard $e) {
            throw new NotUnderstood('What was read from the sentence is not a card.', previous: $e);
        }

        return new Interpreted($card, self::saidByThePlayer($reading->unplaced, $sentence));
    }

    /**
     * @return string the sentence as it is read, without the blanks around it
     *
     * @throws InvalidSentence
     */
    public static function sentence(string $said): string
    {
        // one line of plain text, as a question to the coach is: what a player types, never a page to slip lines into
        $sentence = trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $said));
        if ('' === $sentence || mb_strlen($sentence) > self::MAX_SENTENCE_LENGTH) {
            throw new InvalidSentence(\sprintf('The sentence is 1 to %d characters.', self::MAX_SENTENCE_LENGTH));
        }

        return $sentence;
    }

    /**
     * @param list<string> $pieces
     *
     * @return list<string> those the sentence holds, as the sentence wrote them, each once
     */
    private static function saidByThePlayer(array $pieces, string $sentence): array
    {
        $kept = [];
        foreach ($pieces as $piece) {
            $piece = trim($piece);
            $at = '' === $piece ? false : mb_stripos($sentence, $piece);
            $found = false === $at ? '' : mb_substr($sentence, $at, mb_strlen($piece));
            // kept only when what was cut out of the sentence is the piece, letter for letter but for their case
            if ('' !== $found && mb_strtolower($found) === mb_strtolower($piece)) {
                $kept[] = $found;
            }
        }

        return \array_slice(array_values(array_unique($kept)), 0, self::MOST_UNPLACED);
    }
}
