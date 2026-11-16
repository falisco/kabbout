<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain\Exception;

/**
 * The engine said no: the action is out of turn or against the rules, or a recorded deal cannot be played again.
 * The message is the engine's own. Nothing has changed in the game. It is an ordinary outcome of play,
 * not a mistake in the program: a player may always try a card the rules forbid.
 */
final class EngineRefused extends \RuntimeException implements EngineException
{
    /** @param array<string, int>|null $at where a recorded deal goes wrong: `bid` (from 0), or `trick` (from 1) and `seat` */
    public function __construct(string $message, public readonly ?array $at = null)
    {
        parent::__construct($message);
    }
}
