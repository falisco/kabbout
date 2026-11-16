<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

use Kabbout\Assistant\Domain\Visitor;
use Kabbout\Game\Application\SummariseDeal;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Seat;

/**
 * A visitor's question about a deal, from the door to the answer: what may be asked is counted, the
 * deal is checked by the engine before anything is spent on it, what was already said about the deal
 * is recalled, and only an answer the model wrote is counted and remembered. The page comes in here;
 * so will every other way to the coach.
 */
final readonly class AnswerVisitor
{
    public function __construct(private AskCoach $askCoach, private SummariseDeal $summarise, private CoachAllowance $allowance, private CoachMemory $memory)
    {
    }

    /**
     * @throws \InvalidArgumentException            for a question that is not one
     * @throws AskedTooOften|CoachResting|DealSpent when the question may not be asked now
     * @throws EngineRefused                        for a record the engine cannot play again
     * @throws EngineUnavailable                    when the engine cannot be asked
     */
    public function __invoke(Visitor $visitor, DealRecord $record, Seat $seat, Lang $lang, string $question): Answered
    {
        $brief = new CoachBrief($record, $seat, $lang, $question);
        $this->allowance->pace($visitor);
        // a deal the rules do not allow, or an engine that is down, costs the visitor none of their questions
        ($this->summarise)($record, $seat);
        $this->allowance->day();

        // the deal as the server read it, so the same deal written otherwise is still the same deal
        $deal = hash('sha256', json_encode([$record->toArray(), $seat->value], \JSON_THROW_ON_ERROR));
        $thread = $visitor->key.'|'.$deal;
        $left = $this->allowance->take($visitor, $deal);
        try {
            // what was already said about this deal is the server's own memory of it, never the caller's word
            $answer = ($this->askCoach)(new CoachBrief($record, $seat, $lang, $brief->question, $this->memory->earlier($thread)));
        } catch (EngineRefused|EngineUnavailable $e) {
            $this->allowance->giveBack($visitor, $deal);
            throw $e;
        }
        if (AnswerSource::Model !== $answer->source) {
            // The plain summary is no turn of a conversation. It is not counted either when the model gave
            // nothing; when the model answered and the guard sent it back, the model was paid and the question counts.
            return new Answered($answer, [] === $answer->rejected ? $this->allowance->giveBack($visitor, $deal) : $left);
        }
        $this->memory->remember($thread, new Exchange($brief->question, $answer->text));

        return new Answered($answer, $left);
    }
}
