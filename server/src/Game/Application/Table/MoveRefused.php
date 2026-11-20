<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\LegalMoves;

/**
 * The engine refused the bid, the card or the next deal. The game is as it was. The message is the
 * engine's own reason; the moves it does allow come with it, for the door to say as its client reads them.
 */
final class MoveRefused extends \RuntimeException implements TableRefusal
{
    public function __construct(string $why, public readonly LegalMoves $legal)
    {
        parent::__construct($why);
    }
}
