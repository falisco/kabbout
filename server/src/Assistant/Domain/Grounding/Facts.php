<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain\Grounding;

/**
 * What the tools established in one conversation: every number and every card found anywhere in their
 * results. An answer may state these and nothing else. A number counts whatever its sign: "-80" in a
 * result lets the answer say "80 points less". The number printed on a card counts too: the 9 of "9C".
 */
final class Facts
{
    /** @var array<int, true> */
    private array $numbers = [];
    /** @var array<string, true> */
    private array $cards = [];
    /** @var array<int, true> */
    private array $tricks = [];
    private int $results = 0;

    /** One more tool result, as the data it returned. */
    public function add(mixed $result): void
    {
        ++$this->results;
        $this->read($result, null);
    }

    public function isEmpty(): bool
    {
        return 0 === $this->results;
    }

    public function hasNumber(int $number): bool
    {
        return isset($this->numbers[abs($number)]);
    }

    public function hasCard(string $card): bool
    {
        return isset($this->cards[$card]);
    }

    /** whether a tool result spoke of that trick by its number */
    public function hasTrick(int $number): bool
    {
        return isset($this->tricks[$number]);
    }

    private function read(mixed $value, int|string|null $field): void
    {
        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                $this->read($item, $key);
            }

            return;
        }
        if (\is_int($value) || (\is_float($value) && is_finite($value))) {
            $number = abs((int) round($value));
            $this->numbers[$number] = true;
            if ('trick' === $field) {
                $this->tricks[$number] = true;
            }

            return;
        }
        if (\is_string($value) && 1 === preg_match('/^[789TJQKA][SHDC]$/', $value)) {
            $this->cards[$value] = true;
            // the number printed on a card is a number of the result: "your 10", "the 7, 8 and 9 of clubs"
            $rank = 'T' === $value[0] ? 10 : (int) $value[0];
            if ($rank > 0) {
                $this->numbers[$rank] = true;
            }
        }
    }
}
