<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Analysis;

/**
 * The auction of a deal looked at again for one seat: at each of its turns to speak, what it said and
 * what the expert robot says with the same cards, having heard the same bids, with the expert's reasons.
 * The expert bids by conventions: this compares the seat with them. It does not measure what a bid was worth.
 */
final readonly class BidReview
{
    /**
     * @param list<string>                                       $hand  the seat's eight cards
     * @param list<array{suit: string, value: int, why: string}> $suits what the hand is worth as the taker's with each suit as trumps; 0 when it cannot take there
     * @param list<array{
     *     turn: int,
     *     heard: list<array<string, int|string>>,
     *     standing: array{seat: int, value: int, suit: string}|null,
     *     bid: array<string, int|string>,
     *     expert: array<string, int|string>,
     *     why: string,
     *     same: bool
     * }> $turns the seat's turns to speak, the first one first
     */
    public function __construct(public array $hand, public array $suits, public array $turns)
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['hand' => $this->hand, 'suits' => $this->suits, 'turns' => $this->turns];
    }
}
