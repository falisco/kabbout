<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

/** A question already asked about a deal, with the answer the player read. */
final readonly class Exchange
{
    public function __construct(public string $question, public string $answer)
    {
    }
}
