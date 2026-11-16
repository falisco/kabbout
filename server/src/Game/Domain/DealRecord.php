<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

use Kabbout\Game\Domain\Exception\InvalidDealRecord;

/**
 * A deal as the table saw it: the count, the dealer, the auction and the eight tricks. It comes from
 * a client and is not taken at its word. Its shape is checked here, and only the fields a record is
 * made of are kept; whether the bids and the cards are allowed is for the engine, which plays the
 * deal again and refuses what the rules forbid.
 */
final readonly class DealRecord
{
    private const int TRICKS = 8;
    private const int SEATS = 4;
    /** nine raises, a contre, a surcontre and the passes between them fit well within */
    private const int MAX_BIDS = 64;
    private const array BID_TYPES = ['pass', 'contre', 'surcontre', 'bid'];

    /**
     * @param list<array{seat: int, type: string, value?: int, suit?: string}> $bids   the auction in order
     * @param list<list<array{seat: int, card: string}>>                       $tricks eight tricks, each as its four plays in order
     */
    private function __construct(public Scoring $scoring, public Seat $dealer, public array $bids, public array $tricks)
    {
    }

    /**
     * @param array<array-key, mixed> $data the record as decoded from what the client sent
     *
     * @throws InvalidDealRecord
     */
    public static function fromArray(array $data): self
    {
        $scoring = \is_string($data['scoring'] ?? null) ? Scoring::tryFrom($data['scoring']) : null;
        $dealer = \is_int($data['dealer'] ?? null) ? Seat::tryFrom($data['dealer']) : null;
        $bids = $data['bids'] ?? null;
        $tricks = $data['tricks'] ?? null;
        if (null === $scoring || null === $dealer) {
            throw new InvalidDealRecord('A recorded deal names its count and its dealer.');
        }
        if (!\is_array($bids) || !array_is_list($bids) || \count($bids) > self::MAX_BIDS) {
            throw new InvalidDealRecord('A recorded deal holds its auction, bid by bid.');
        }
        if (!\is_array($tricks) || !array_is_list($tricks) || self::TRICKS !== \count($tricks)) {
            throw new InvalidDealRecord('A recorded deal holds eight tricks.');
        }

        return new self($scoring, $dealer, array_map(self::bid(...), $bids), array_map(self::trick(...), $tricks));
    }

    /** @return array{scoring: string, dealer: int, bids: list<array<string, int|string>>, tricks: list<list<array<string, int|string>>>} */
    public function toArray(): array
    {
        return ['scoring' => $this->scoring->value, 'dealer' => $this->dealer->value, 'bids' => $this->bids, 'tricks' => $this->tricks];
    }

    /** @return array{seat: int, type: string, value?: int, suit?: string} */
    private static function bid(mixed $bid): array
    {
        if (!\is_array($bid) || !\is_int($bid['seat'] ?? null) || !\in_array($bid['type'] ?? null, self::BID_TYPES, true)) {
            throw new InvalidDealRecord('A bid of a recorded deal has a seat and says what it is.');
        }
        $kept = ['seat' => $bid['seat'], 'type' => $bid['type']];
        if ('bid' !== $bid['type']) {
            return $kept;
        }
        if (!\is_int($bid['value'] ?? null) || !\is_string($bid['suit'] ?? null) || 1 !== \strlen($bid['suit'])) {
            throw new InvalidDealRecord('A raise of a recorded deal has a value and a suit.');
        }

        return [...$kept, 'value' => $bid['value'], 'suit' => $bid['suit']];
    }

    /** @return list<array{seat: int, card: string}> */
    private static function trick(mixed $plays): array
    {
        if (!\is_array($plays) || !array_is_list($plays) || self::SEATS !== \count($plays)) {
            throw new InvalidDealRecord('A trick of a recorded deal is four plays.');
        }

        return array_map(static function (mixed $play): array {
            $card = \is_array($play) && \is_string($play['card'] ?? null) ? Card::tryFromCode($play['card']) : null;
            if (!\is_array($play) || !\is_int($play['seat'] ?? null) || null === $card) {
                throw new InvalidDealRecord('A play of a recorded deal has a seat and a card.');
            }

            return ['seat' => $play['seat'], 'card' => $card->code()];
        }, $plays);
    }
}
