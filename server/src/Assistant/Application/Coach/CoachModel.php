<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

use Kabbout\Assistant\Domain\Grounding\Facts;

/**
 * The language model behind the coach, with the tools that look at the deal. It only talks: whatever
 * its tools return while it works is added to `$facts`, and the answer is checked against them by the caller.
 */
interface CoachModel
{
    /**
     * @param Rejected|null $rejected a first answer that stated things no tool result holds, to be written again
     * @param float|null    $until    the time (in seconds, as microtime gives it) past which the model looks at the deal no more and must answer
     *
     * @throws CoachModelFailed when no answer could be had: the platform is down, the model went in circles
     */
    public function answer(CoachBrief $brief, Facts $facts, ?Rejected $rejected = null, ?float $until = null): string;
}
