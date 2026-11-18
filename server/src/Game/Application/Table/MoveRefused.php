<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\LegalMoves;

/** The engine refused the bid, the card or the next deal. The game is as it was; these are the moves it allows. */
final class MoveRefused extends \RuntimeException implements TableRefusal
{
    public function __construct(string $why, public readonly LegalMoves $legal)
    {
        parent::__construct(\sprintf('Refused: %s Nothing has changed. What you may do now: %s', $why, json_encode($legal->toArray(), \JSON_THROW_ON_ERROR)));
    }
}
