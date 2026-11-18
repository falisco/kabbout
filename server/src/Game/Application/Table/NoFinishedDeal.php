<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

/** A deal is reviewed once its eight tricks are played: before that, a review would show cards still hidden. */
final class NoFinishedDeal extends \RuntimeException implements TableRefusal
{
    public function __construct()
    {
        parent::__construct('No deal has been played to its end at this table yet. A deal is reviewed once its eight tricks are played.');
    }
}
