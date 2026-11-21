<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

use Kabbout\Game\Domain\Exception\InvalidConventionCard;

/**
 * What two partners agreed their bids mean: what a hand must be worth to open, what the partner adds
 * for what it holds in the suit named, and when it shows its outside aces. A closed set of settings,
 * each with the few values it may take; the robots bid by one.
 *
 * The engine says what a card is (engine/src/ai/conventions.ts). The lists are held here too, so that
 * what is not a card is refused before anything is asked of the engine; bridge/contract/conventions.json,
 * written from the engine, is replayed by the tests of both sides and keeps the two alike.
 *
 * A card belongs to nobody here: whose it is, and where it is kept, is for whoever holds it.
 */
final readonly class ConventionCard
{
    /** each setting with the values it may take */
    public const array VALUES = [
        'open' => [70, 80, 90],
        'jack' => [0, 10, 20, 30],
        'nine' => [0, 10, 20],
        'threeTrumps' => [0, 10, 20],
        'belote' => [0, 10, 20, 30],
        'ace' => [0, 10, 20],
        'aces' => ['atOnce', 'whenOverbid'],
    ];
    /** the cards ready to pick; the classic one is how the robots bid when they are given none */
    public const array READY = [
        'classic' => ['open' => 70, 'jack' => 20, 'nine' => 10, 'threeTrumps' => 10, 'belote' => 20, 'ace' => 10, 'aces' => 'atOnce'],
        'cautious' => ['open' => 80, 'jack' => 20, 'nine' => 10, 'threeTrumps' => 0, 'belote' => 20, 'ace' => 10, 'aces' => 'atOnce'],
        'bold' => ['open' => 70, 'jack' => 30, 'nine' => 20, 'threeTrumps' => 10, 'belote' => 20, 'ace' => 10, 'aces' => 'whenOverbid'],
    ];

    /** @param array<string, int|string> $settings every setting of VALUES, in its order, each with one of its values */
    private function __construct(private array $settings)
    {
    }

    /**
     * @param array<mixed> $given
     *
     * @throws InvalidConventionCard
     */
    public static function fromArray(array $given): self
    {
        if ([] !== array_diff_key($given, self::VALUES)) {
            throw new InvalidConventionCard(\sprintf('A conventions card holds %s, and nothing else.', implode(', ', array_keys(self::VALUES))));
        }
        $settings = [];
        foreach (self::VALUES as $setting => $allowed) {
            $value = $given[$setting] ?? null;
            if ((!\is_int($value) && !\is_string($value)) || !\in_array($value, $allowed, true)) {
                throw new InvalidConventionCard(\sprintf('On a conventions card, %s is one of %s.', $setting, implode(', ', $allowed)));
            }
            $settings[$setting] = $value;
        }

        return new self($settings);
    }

    public static function classic(): self
    {
        return new self(self::READY['classic']);
    }

    /** the ready card of that name, or null when there is none */
    public static function ready(string $name): ?self
    {
        return isset(self::READY[$name]) ? new self(self::READY[$name]) : null;
    }

    /**
     * The ready card somebody asked for by its name; none asked for, none given.
     *
     * @throws InvalidConventionCard for a name no ready card has
     */
    public static function named(?string $name): ?self
    {
        return null === $name ? null : self::ready($name) ?? throw new InvalidConventionCard(\sprintf('The ready conventions cards are %s.', implode(', ', array_keys(self::READY))));
    }

    public function equals(self $other): bool
    {
        return $this->settings === $other->settings;
    }

    /** @return array<string, int|string> the card as the engine and the page read it */
    public function toArray(): array
    {
        return $this->settings;
    }
}
