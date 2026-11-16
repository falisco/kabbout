<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Ai;

use Kabbout\Assistant\Application\Coach\CoachBrief;
use Kabbout\Assistant\Domain\Grounding\Facts;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Seat;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The deal the coach is being asked about, for as long as its agent runs. The agent and its tools are
 * services built once; the deal changes with every question. The model opens the conversation before it
 * calls the agent and closes it after: the tools read the deal here, and write here what they return.
 */
final class CoachConversation implements ResetInterface
{
    private ?CoachBrief $brief = null;
    private ?Facts $facts = null;

    public function open(CoachBrief $brief, Facts $facts): void
    {
        $this->brief = $brief;
        $this->facts = $facts;
    }

    public function close(): void
    {
        $this->brief = null;
        $this->facts = null;
    }

    public function reset(): void
    {
        $this->close();
    }

    public function record(): DealRecord
    {
        return $this->brief()->record;
    }

    public function seat(): Seat
    {
        return $this->brief()->seat;
    }

    /** One more tool result, as the data it returned: what the answer will be checked against. */
    public function establish(mixed $result): void
    {
        ($this->facts ?? throw self::closed())->add($result);
    }

    private function brief(): CoachBrief
    {
        return $this->brief ?? throw self::closed();
    }

    private static function closed(): \LogicException
    {
        return new \LogicException('A tool of the coach was called outside a conversation.');
    }
}
