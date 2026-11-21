<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

/** An answer the guard did not let through, with what it stated without a tool result behind it. */
final readonly class Rejected
{
    /** @param non-empty-list<string> $ungrounded numbers in digits, cards as codes */
    public function __construct(public string $answer, public array $ungrounded)
    {
    }
}
