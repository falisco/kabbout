<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

/** What a visitor gets back: the answer, and how many questions about this deal are left to them. */
final readonly class Answered
{
    public function __construct(public CoachAnswer $answer, public int $left)
    {
    }
}
