<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

use Kabbout\Assistant\Domain\Grounding\Facts;
use Kabbout\Assistant\Domain\Grounding\GroundingGuard;
use Kabbout\Assistant\Domain\Grounding\Reading;
use Kabbout\Game\Application\RankSwings;
use Kabbout\Game\Application\SummariseDeal;
use Kabbout\Game\Domain\Exception\EngineRefused;

/**
 * A question about a deal, answered. The engine plays the record first, so a deal the rules do not
 * allow is refused before any model is asked. The model's answer is then held to the guard: one
 * invention gets it one more try, a second one and the player reads the plain summary instead.
 * Whatever happens to the model, the player gets an answer made of the engine's numbers.
 */
final readonly class AskCoach
{
    /** an answer is a few sentences; past this the model has stopped listening */
    public const int MAX_ANSWER_LENGTH = 1500;
    /** seconds the model's tools may be called for one question: past them the model must answer with what it has */
    public const float LOOKING_SECONDS = 30.0;
    /** a rejected answer is tried again only if the first one came within this many seconds: a player is waiting */
    public const float SECOND_TRY_WITHIN = 20.0;

    /** @var \Closure(): float */
    private \Closure $now;

    /** @param (\Closure(): float)|null $now the time in seconds; the clock of the machine unless a test says otherwise */
    public function __construct(private CoachModel $model, private SummariseDeal $summarise, private RankSwings $rankSwings, ?\Closure $now = null)
    {
        $this->now = $now ?? static fn (): float => microtime(true);
    }

    /** @throws EngineRefused for a record the engine cannot play again, with the place where it goes wrong */
    public function __invoke(CoachBrief $brief): CoachAnswer
    {
        $summary = ($this->summarise)($brief->record, $brief->seat);
        $facts = new Facts();
        $refused = [];
        $started = ($this->now)();
        try {
            $rejected = null;
            // the first answer, then one more if the guard rejects it
            for ($attempt = 0; $attempt < 2; ++$attempt) {
                $text = trim($this->model->answer($brief, $facts, $rejected, $started + self::LOOKING_SECONDS));
                if ('' === $text || mb_strlen($text) > self::MAX_ANSWER_LENGTH || $facts->isEmpty()) {
                    break;
                }
                $ungrounded = GroundingGuard::ungrounded($text, $facts);
                if ([] === $ungrounded) {
                    $reading = Reading::of($text);

                    return new CoachAnswer($text, AnswerSource::Model, array_values(array_filter($reading->tricks, $facts->hasTrick(...))), $reading->cards, $refused);
                }
                $refused[] = $rejected = new Rejected($text, $ungrounded);
                if (($this->now)() - $started > self::SECOND_TRY_WITHIN) {
                    break;
                }
            }
        } catch (CoachModelFailed) {
            // the plain summary below is the answer
        }

        $plain = PlainSummary::of($summary, ($this->rankSwings)($brief->record, $brief->seat), $brief->lang);

        return new CoachAnswer($plain->text, $plain->source, $plain->tricks, $plain->cards, $refused);
    }
}
