<?php

declare(strict_types=1);

namespace Kabbout\Identity\Domain;

/**
 * The name a player goes by at the table. A guest does not choose it: it is the letter C and seven
 * digits (C4821973), drawn at random. Two players never hold the same name: Players sees to that.
 */
final readonly class PlayerName
{
    private const int GUEST_DIGITS = 7;
    private const string GUEST = '/^C[0-9]{'.self::GUEST_DIGITS.'}$/D';
    /** how many names a guest's can be drawn from */
    private const int GUEST_NAMES = 10 ** self::GUEST_DIGITS;

    private function __construct(private string $name)
    {
    }

    /** A guest's name, as chance draws it. */
    public static function guest(Chance $chance): self
    {
        return self::fromString('C'.str_pad((string) $chance->below(self::GUEST_NAMES), self::GUEST_DIGITS, '0', \STR_PAD_LEFT));
    }

    public static function fromString(string $name): self
    {
        return 1 === preg_match(self::GUEST, $name) ? new self($name) : throw new \InvalidArgumentException('A name is the letter C and seven digits.');
    }

    public function toString(): string
    {
        return $this->name;
    }
}
