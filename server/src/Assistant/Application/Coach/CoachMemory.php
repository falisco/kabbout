<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

use Kabbout\Identity\Domain\PlayerId;

/**
 * What was already said about a deal, kept by the server between two questions. The page does not send
 * the conversation back: an earlier answer the coach is shown is one the coach really gave.
 *
 * A conversation is a player's, about a deal. It is kept under who the player is and under nothing
 * else: not a name, not a network address, which several players can share.
 */
interface CoachMemory
{
    /** a deal is a couple of questions; nothing longer is kept */
    public const int MAX_EXCHANGES = 4;

    /**
     * @param string $deal the deal the conversation is about, as AnswerVisitor names it
     *
     * @return list<Exchange> the oldest first; none when nothing is remembered
     */
    public function earlier(PlayerId $player, string $deal): array;

    public function remember(PlayerId $player, string $deal, Exchange $exchange): void;
}
