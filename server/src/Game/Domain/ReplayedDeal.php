<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/** A recorded deal the engine accepted: the hands as dealt, the contract, the tricks with their winners and the score sheet. */
final readonly class ReplayedDeal
{
    /**
     * @param list<list<string>>         $hands    by seat
     * @param array<string, mixed>       $contract
     * @param list<array<string, mixed>> $tricks
     * @param array<string, mixed>       $result   the engine's score sheet
     */
    public function __construct(public array $hands, public array $contract, public array $tricks, public array $result)
    {
    }

    /** @return array{hands: list<list<string>>, contract: array<string, mixed>, tricks: list<array<string, mixed>>, result: array<string, mixed>} */
    public function toArray(): array
    {
        return ['hands' => $this->hands, 'contract' => $this->contract, 'tricks' => $this->tricks, 'result' => $this->result];
    }
}
