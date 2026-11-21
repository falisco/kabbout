<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Ai;

use Kabbout\Assistant\Application\Coach\CoachBrief;
use Kabbout\Assistant\Domain\Grounding\Facts;
use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Lang;
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
    private ?float $until = null;
    private int $looks = 0;

    /** how many tool results one question may gather: the agent's limit is on rounds, and a round may hold many calls */
    public const int MAX_LOOKS = 14;

    /** @param float|null $until the time, as microtime gives it, past which the tools answer no more */
    public function open(CoachBrief $brief, Facts $facts, ?float $until = null): void
    {
        $this->brief = $brief;
        $this->facts = $facts;
        $this->until = $until;
    }

    public function close(): void
    {
        $this->brief = null;
        $this->facts = null;
        $this->until = null;
        $this->looks = 0;
    }

    /**
     * Whether a tool may still look at the deal: not past the time given to the question, and not more
     * often than a question is worth. Each look allowed is counted.
     */
    public function mayLook(): bool
    {
        if (null !== $this->until && microtime(true) > $this->until) {
            return false;
        }

        return ++$this->looks <= self::MAX_LOOKS;
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

    public function lang(): Lang
    {
        return $this->brief()->lang;
    }

    /** the conventions the player and their partner bid by in the deal; none, the classic ones */
    public function card(): ?ConventionCard
    {
        return $this->brief()->card;
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
