<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\GameState;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Phase;
use Kabbout\Game\Domain\Step;

/**
 * What happens at a table after its player's action, or after the cards are dealt: the robots bid and
 * play in turn until the player must act again or the deal is over. Then the table is kept, and the
 * player is told where they stand.
 *
 * Each call to the engine draws from a seed of its own, derived from the table's secret and named
 * after the move it is for: the same table played the same way gives the same game.
 */
final readonly class RobotsPlay
{
    /** between two actions of the player there are three robots, and a few more turns after a contre: far under this */
    private const int MOST_MOVES = 64;

    public function __construct(private Engine $engine, private Tables $tables, private Now $now)
    {
    }

    /**
     * @param Step $step  the game after the action that was just applied, with what the table heard of it
     * @param int  $moves the actions applied so far, that one included
     *
     * @throws EngineRefused
     * @throws EngineUnavailable
     * @throws TablesUnavailable
     */
    public function __invoke(Table $table, Step $step, int $moves): Turn
    {
        $state = $step->state;
        $events = $step->events;
        for ($played = 0; $this->robotToAct($table, $state); ++$played) {
            if (self::MOST_MOVES === $played) {
                throw new \LogicException('The robots play on and on: the player\'s turn never comes.');
            }
            // the reasons a robot gives for its bids are read by the review, in English like the rest of what a client reads
            $move = $this->engine->robotMove($state, $table->level, $table->secret->derive('robot '.$moves), Lang::En);
            $step = $this->engine->apply($state, $move->seat, $move->action, $table->secret->derive('move '.$moves));
            $state = $step->state;
            $events = [...$events, ...$step->events];
            ++$moves;
        }
        $view = $this->engine->view($state, $table->seat);
        $finished = Phase::RoundEnd === $state->phase() ? $view->dealRecord() : null;
        $this->tables->save($table->after($state, $moves, $finished, $this->now->seconds()));

        return new Turn($table->id, $events, $view, $this->engine->legalMoves($state, $table->seat));
    }

    private function robotToAct(Table $table, GameState $state): bool
    {
        return \in_array($state->phase(), [Phase::Bidding, Phase::Playing], true) && $state->turn() !== $table->seat;
    }
}
