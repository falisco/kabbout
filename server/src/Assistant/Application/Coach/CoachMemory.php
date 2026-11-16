<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

/**
 * What was already said about a deal, kept by the server between two questions. The page does not send
 * the conversation back: an earlier answer the coach is shown is one the coach really gave.
 */
interface CoachMemory
{
    /** a deal is a couple of questions; nothing longer is kept */
    public const int MAX_EXCHANGES = 4;

    /**
     * @param string $thread whose conversation, about which deal
     *
     * @return list<Exchange> the oldest first; none when nothing is remembered
     */
    public function earlier(string $thread): array;

    public function remember(string $thread, Exchange $exchange): void;
}
