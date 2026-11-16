<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

/**
 * What a seat asks the engine to do: a bid, a card, or the next deal once one is over.
 * Whether it is allowed is for the engine to say, never for the caller.
 */
final readonly class Action
{
    /** @param array<string, mixed> $data */
    private function __construct(private array $data)
    {
    }

    public static function pass(): self
    {
        return new self(['kind' => 'bid', 'bid' => ['type' => 'pass']]);
    }

    public static function contre(): self
    {
        return new self(['kind' => 'bid', 'bid' => ['type' => 'contre']]);
    }

    public static function surcontre(): self
    {
        return new self(['kind' => 'bid', 'bid' => ['type' => 'surcontre']]);
    }

    /** @param int $value 80 to 160 by tens, or 250 for the capot */
    public static function bid(int $value, Suit $suit): self
    {
        return new self(['kind' => 'bid', 'bid' => ['type' => 'bid', 'value' => $value, 'suit' => $suit->value]]);
    }

    public static function play(Card $card): self
    {
        return new self(['kind' => 'play', 'card' => $card->code()]);
    }

    public static function next(): self
    {
        return new self(['kind' => 'next']);
    }

    /**
     * An action as the engine wrote it, such as a robot's. A robot's bid carries its reason (`why`),
     * which names its cards: it goes back to the engine with the action and to nobody else.
     * An action that comes from a player is never built this way: the constructors above carry no reason.
     *
     * @param array<string, mixed> $data
     */
    public static function fromEngine(array $data): self
    {
        return new self($data);
    }

    /** The same action as the table hears it: without the reason a robot gave for it. */
    public function withoutReason(): self
    {
        $data = $this->data;
        unset($data['why']);

        return new self($data);
    }

    /**
     * For the engine only: a robot's action holds its reason.
     *
     * @return array<string, mixed>
     */
    public function toEngineArray(): array
    {
        return $this->data;
    }
}
