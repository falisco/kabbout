<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

/** What the player reads, and what it points at: the tricks and the cards it names, for the screen to show them. */
final readonly class CoachAnswer
{
    /**
     * @param list<int>    $tricks trick numbers, from 1
     * @param list<string> $cards  as the engine writes them
     */
    public function __construct(public string $text, public AnswerSource $source, public array $tricks, public array $cards)
    {
    }
}
