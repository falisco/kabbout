<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

use Kabbout\Assistant\Application\Allowance\AskedTooOften;
use Kabbout\Assistant\Application\Allowance\DaySpent;
use Kabbout\Game\Application\Analysis\SummariseDeal;
use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Seat;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayerId;

/**
 * A question about a deal, from the door to the answer: what may be asked is counted, the
 * deal is checked by the engine before anything is spent on it, what was already said about the deal
 * is recalled, and only an answer the model wrote is counted and remembered. The page comes in here;
 * so will every other way to the coach.
 *
 * Two things are known of who asks, and they are not the same. What is counted is counted against the
 * network address the question comes from: a guest costs nothing to make, so a count per guest would be
 * no limit. What is remembered is remembered for the player. Whoever asks as nobody the server knows is
 * answered like anybody, and nothing is remembered: each of their questions is a first question.
 */
final readonly class AnswerVisitor
{
    public function __construct(private AskCoach $askCoach, private SummariseDeal $summarise, private CoachAllowance $allowance, private CoachMemory $memory)
    {
    }

    /**
     * @param NetworkAddress $from   where the question comes from: what it is counted against
     * @param PlayerId|null  $player who asks, when the server knows them: whose conversation it is
     *
     * @throws \InvalidArgumentException        for a question that is not one
     * @throws AskedTooOften|DaySpent|DealSpent when the question may not be asked now
     * @throws EngineRefused                    for a record the engine cannot play again
     * @throws EngineUnavailable                when the engine cannot be asked
     */
    public function __invoke(NetworkAddress $from, ?PlayerId $player, DealRecord $record, Seat $seat, Lang $lang, string $question, ?ConventionCard $card = null): Answered
    {
        $brief = new CoachBrief($record, $seat, $lang, $question);
        $this->allowance->pace($from);
        // a deal the rules do not allow, or an engine that is down, costs the visitor none of their questions
        ($this->summarise)($record, $seat);

        // the deal as the server read it, so the same deal written otherwise is still the same deal
        $deal = hash('sha256', json_encode([$record->toArray(), $seat->value], \JSON_THROW_ON_ERROR));
        $left = $this->allowance->take($from, $deal);
        try {
            // counted last: a question refused for its deal must not use up the day of everybody else
            $this->allowance->day();
        } catch (DaySpent $e) {
            $this->allowance->giveBack($from, $deal);
            throw $e;
        }
        try {
            // what was already said about this deal is the server's own memory of it, never the caller's word
            $earlier = null === $player ? [] : $this->memory->earlier($player, $deal);
            $answer = ($this->askCoach)(new CoachBrief($record, $seat, $lang, $brief->question, $earlier, $card));
        } catch (EngineRefused|EngineUnavailable $e) {
            $this->allowance->giveBack($from, $deal);
            throw $e;
        }
        if (AnswerSource::Model !== $answer->source) {
            // The plain summary is no turn of a conversation. It is not counted either when the model gave
            // nothing; when the model answered and the guard sent it back, the model was paid and the question counts.
            return new Answered($answer, [] === $answer->rejected ? $this->allowance->giveBack($from, $deal) : $left);
        }
        if (null !== $player) {
            $this->memory->remember($player, $deal, new Exchange($brief->question, $answer->text));
        }

        return new Answered($answer, $left);
    }
}
