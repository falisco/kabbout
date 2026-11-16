<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Analysis;

use Kabbout\Game\Domain\Scoring;
use Kabbout\Game\Domain\Seat;

/**
 * A deal in a few lines, seen from one seat: the auction, the contract, and what the seat's team (`us`)
 * and the other (`them`) made of it.
 */
final readonly class DealSummary
{
    /**
     * @param list<array<string, int|string>>                       $bids     the auction in order, each bid with its seat
     * @param array{seat: int, value: int, suit: string, mult: int} $contract `mult` is 2 when contred, 4 when surcontred
     * @param bool                                                  $taking   whether the seat's team took the contract
     * @param 'us'|'them'|null                                      $belote   the team that held king and queen of trumps
     */
    public function __construct(
        public Seat $seat,
        public Scoring $scoring,
        public Seat $dealer,
        public array $bids,
        public array $contract,
        public bool $taking,
        public bool $made,
        public ?string $belote,
        public Side $us,
        public Side $them,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'seat' => $this->seat->value,
            'scoring' => $this->scoring->value,
            'dealer' => $this->dealer->value,
            'bids' => $this->bids,
            'contract' => $this->contract,
            'taking' => $this->taking,
            'made' => $this->made,
            'belote' => $this->belote,
            'us' => $this->us->toArray(),
            'them' => $this->them->toArray(),
        ];
    }
}
