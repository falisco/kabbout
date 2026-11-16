<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Analysis;

/** A seat's costly decisions in a deal, the costliest first. A card the expert would also have played is never among them. */
final readonly class Swings
{
    /**
     * @param list<Swing> $swings
     * @param int         $choices tricks where the seat had more than one legal card
     */
    public function __construct(public array $swings, public int $choices)
    {
    }

    public function worst(): ?Swing
    {
        return $this->swings[0] ?? null;
    }

    /** @return array{swings: list<array{trick: int, played: string, choice: string, loss: int}>, choices: int} */
    public function toArray(): array
    {
        return ['swings' => array_map(static fn (Swing $swing): array => $swing->toArray(), $this->swings), 'choices' => $this->choices];
    }
}
