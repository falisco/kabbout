<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/**
 * What one seat may know of a game: its own hand and everything public. This is the only picture of
 * a game that may be sent to a player, a client or a model. It is built by the engine; this class is
 * a second lock: it only lets through the fields a view is known to be made of, so that nothing
 * stored with a game, and no field added to the engine's state without a thought for this list, can
 * reach a player unnoticed.
 */
final readonly class SeatView
{
    /** the public fields of a game, then the seat's own: none other is allowed */
    private const array FIELDS = [
        'target', 'scoring', 'scores', 'history', 'dealer', 'round', 'phase', 'turn', 'bids', 'best', 'coinche',
        'contreQueue', 'passes', 'contract', 'trick', 'tricks', 'belote', 'result', 'winner',
        'seat', 'hand', 'handSizes',
    ];

    private Seat $seat;
    /** @var list<string> */
    private array $hand;

    /** @param array<string, mixed> $data */
    public function __construct(private array $data)
    {
        $unknown = array_diff(array_keys($data), self::FIELDS);
        if ([] !== $unknown) {
            throw new \InvalidArgumentException(\sprintf('Not a seat view: it holds "%s", which a view is not made of.', implode('", "', $unknown)));
        }
        $seat = $data['seat'] ?? null;
        $this->seat = (\is_int($seat) ? Seat::tryFrom($seat) : null) ?? throw new \InvalidArgumentException('Not a seat view: no seat.');
        $hand = $data['hand'] ?? null;
        if (!\is_array($hand) || !array_is_list($hand) || $hand !== array_filter($hand, \is_string(...))) {
            throw new \InvalidArgumentException('Not a seat view: no hand.');
        }
        $this->hand = $hand;
        $bids = $data['bids'] ?? [];
        foreach (\is_array($bids) ? $bids : [] as $bid) {
            if (\is_array($bid) && \array_key_exists('why', $bid)) {
                throw new \InvalidArgumentException('Not a seat view: a bid carries its robot\'s reason.');
            }
        }
    }

    public function seat(): Seat
    {
        return $this->seat;
    }

    /** @return list<string> the seat's own cards */
    public function hand(): array
    {
        return $this->hand;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
