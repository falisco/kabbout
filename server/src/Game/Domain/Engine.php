<?php

declare(strict_types=1);

namespace Kabbout\Game\Domain;

use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;

/**
 * The rules of the game, as the server uses them. They are written once, in TypeScript, and shared
 * with the game in the browser; the server reaches them through this door and keeps no rule of its own.
 *
 * The engine keeps nothing between two calls: the game travels with each of them. Every call that may
 * draw at random takes a seed, so the same question always gets the same answer. A seed serves one call:
 * see Seed for how a game derives a new one each time.
 *
 * @throws EngineRefused     when the engine says no (declared on the calls that can be refused)
 * @throws EngineUnavailable on any call, when the engine cannot be reached
 */
interface Engine
{
    /** Deals the first hand. Without a dealer, the seed chooses one. */
    public function newGame(Seed $seed, Scoring $scoring = Scoring::Ffb, ?int $target = null, ?Seat $dealer = null): Step;

    /**
     * One action of one seat. The seed deals the next cards when the action calls for a new deal.
     *
     * @throws EngineRefused
     */
    public function apply(GameState $state, Seat $seat, Action $action, Seed $seed): Step;

    /** What one seat may know of the game. */
    public function view(GameState $state, Seat $seat): SeatView;

    public function legalMoves(GameState $state, Seat $seat): LegalMoves;

    /**
     * What a robot does for the seat to act, from that seat's view alone.
     *
     * @param Lang                $lang the language of the reason a robot gives for its bid
     * @param ConventionCard|null $card the conventions that seat and its partner bid by; none, the classic ones
     *
     * @throws EngineRefused when no seat is to bid or to play
     */
    public function robotMove(GameState $state, Level $level, Seed $seed, Lang $lang = Lang::Fr, ?ConventionCard $card = null): RobotMove;

    /**
     * Plays a recorded deal again.
     *
     * @throws EngineRefused when the rules do not allow the record
     */
    public function replay(DealRecord $record): ReplayedDeal;
}
