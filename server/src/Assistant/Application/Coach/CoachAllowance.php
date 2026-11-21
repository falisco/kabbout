<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

use Kabbout\Assistant\Application\ModelAllowance;
use Kabbout\Identity\Domain\NetworkAddress;

/**
 * How much the coach may be asked: what any call to the model is counted against (ModelAllowance), and
 * one count of its own, a visitor's questions about one deal. Every way in to the coach (the page
 * today, others tomorrow) goes through them.
 */
interface CoachAllowance extends ModelAllowance
{
    /**
     * Takes one of the visitor's questions about a deal.
     *
     * @param string $deal what names the deal for this visitor
     *
     * @return int how many are left after this one
     *
     * @throws DealSpent when none is left
     */
    public function take(NetworkAddress $from, string $deal): int;

    /**
     * Gives back a question taken, when it turned out to cost nothing: the engine could not be asked, or the model gave no answer.
     *
     * @return int how many are left
     */
    public function giveBack(NetworkAddress $from, string $deal): int;
}
