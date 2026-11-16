<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Ai;

use Kabbout\Assistant\Application\Coach\CoachBrief;
use Kabbout\Assistant\Domain\Grounding\Facts;
use Kabbout\Game\Application\AskExpertChoice;
use Kabbout\Game\Application\RankSwings;
use Kabbout\Game\Application\ReviewTrick;
use Kabbout\Game\Application\SummariseDeal;
use Kabbout\Game\Application\TryWhatIf;

/** The tools of one conversation: bound to its deal, its seat and its facts. */
final readonly class CoachToolsFactory
{
    public function __construct(
        private SummariseDeal $summarise,
        private ReviewTrick $reviewTrick,
        private AskExpertChoice $askExpertChoice,
        private TryWhatIf $tryWhatIf,
        private RankSwings $rankSwings,
    ) {
    }

    public function for(CoachBrief $brief, Facts $facts): CoachTools
    {
        return new CoachTools($brief->record, $brief->seat, $facts, $this->summarise, $this->reviewTrick, $this->askExpertChoice, $this->tryWhatIf, $this->rankSwings);
    }
}
