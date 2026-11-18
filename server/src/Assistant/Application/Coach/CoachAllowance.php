<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

use Kabbout\Identity\Domain\NetworkAddress;

/**
 * How much the coach may be asked. Every question is a call to a model that is paid for, so three counts
 * stand before it: a visitor's pace, what all visitors together may ask in a day, and a visitor's
 * questions about one deal. Every way in to the coach (the page today, others tomorrow) goes through them.
 */
interface CoachAllowance
{
    /** @throws AskedTooOften when this visitor asks faster than allowed */
    public function pace(NetworkAddress $from): void;

    /** @throws CoachResting when the day's questions, all visitors together, are spent */
    public function day(): void;

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
