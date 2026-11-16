<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure\Bridge\Mapping;

use Kabbout\Game\Domain\Action;
use Kabbout\Game\Domain\ActionKind;
use Kabbout\Game\Domain\Bid;
use Kabbout\Game\Domain\BidType;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Suit;

/** An action as the engine writes it and reads it. The domain knows nothing of this shape. */
final class ActionMapper
{
    /** @return array<string, mixed> */
    public static function toArray(Action $action): array
    {
        return match ($action->kind) {
            ActionKind::Next => ['kind' => 'next'],
            ActionKind::Play => ['kind' => 'play', 'card' => $action->card?->code()],
            ActionKind::Bid => ['kind' => 'bid', 'bid' => self::bidToArray($action->bid), ...(null === $action->reason ? [] : ['why' => $action->reason])],
        };
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws EngineUnavailable when the bridge wrote something that is not an action
     */
    public static function fromArray(array $data): Action
    {
        $kind = \is_string($data['kind'] ?? null) ? ActionKind::tryFrom($data['kind']) : null;
        $why = $data['why'] ?? null;

        return match (true) {
            ActionKind::Next === $kind => Action::next(),
            ActionKind::Play === $kind && \is_string($data['card'] ?? null) => Action::play(Card::tryFromCode($data['card']) ?? throw self::notAnAction()),
            ActionKind::Bid === $kind && (null === $why || \is_string($why)) => Action::bid(self::bidFromArray($data['bid'] ?? null), $why),
            default => throw self::notAnAction(),
        };
    }

    /** @return array<string, int|string> */
    private static function bidToArray(?Bid $bid): array
    {
        return match (true) {
            null === $bid => throw new \LogicException('A bid action holds its bid.'),
            null !== $bid->value && null !== $bid->suit => ['type' => $bid->type->value, 'value' => $bid->value, 'suit' => $bid->suit->value],
            default => ['type' => $bid->type->value],
        };
    }

    private static function bidFromArray(mixed $data): Bid
    {
        $type = \is_array($data) && \is_string($data['type'] ?? null) ? BidType::tryFrom($data['type']) : null;
        if (BidType::Raise !== $type) {
            return match ($type) {
                BidType::Pass => Bid::pass(),
                BidType::Contre => Bid::contre(),
                BidType::Surcontre => Bid::surcontre(),
                null => throw self::notAnAction(),
            };
        }
        $suit = \is_string($data['suit'] ?? null) ? Suit::tryFrom($data['suit']) : null;
        if (!\is_int($data['value'] ?? null) || null === $suit) {
            throw self::notAnAction();
        }

        return Bid::raise($data['value'], $suit);
    }

    private static function notAnAction(): EngineUnavailable
    {
        return new EngineUnavailable('The bridge answered an action that is not one.');
    }
}
