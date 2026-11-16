<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Seat;

/**
 * What the coach is asked: a question about a recorded deal, by the player of one seat, in one language,
 * after what was already said about that deal.
 */
final readonly class CoachBrief
{
    public const int MAX_QUESTION_LENGTH = 300;

    public string $question;

    /**
     * @param list<Exchange> $earlier the questions already answered about this deal, the oldest first
     *
     * @throws \InvalidArgumentException for an empty question or one longer than MAX_QUESTION_LENGTH characters
     */
    public function __construct(public DealRecord $record, public Seat $seat, public Lang $lang, string $question, public array $earlier = [])
    {
        // one line of plain text: what a player types, never a page to slip instructions in
        $question = trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $question));
        if ('' === $question || mb_strlen($question) > self::MAX_QUESTION_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('A question to the coach is 1 to %d characters.', self::MAX_QUESTION_LENGTH));
        }
        $this->question = $question;
    }
}
