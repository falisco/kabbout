<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Application\Table;

use Kabbout\Game\Domain\Action;
use Kabbout\Game\Domain\ActionKind;
use Kabbout\Game\Domain\Bid;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\GameState;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\LegalMoves;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\ReplayedDeal;
use Kabbout\Game\Domain\RobotMove;
use Kabbout\Game\Domain\Scoring;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\SeatView;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Domain\Step;

/**
 * An engine for tests that knows one rule: the seats act in turn, and a deal is over after so many
 * actions. It writes down every call that draws, with its seed, and refuses whoever acts out of turn.
 */
final class TurnsEngine implements Engine
{
    /** @var list<array{string, int, string, string}> what was applied: who asked ("robot" or "apply"), the seat, the kind of action, the seed */
    public array $drawn = [];
    /** @var list<string|null> the reason each applied action carried */
    public array $reasons = [];

    /** @param int $actions how many actions a deal lasts */
    public function __construct(private readonly int $actions = 6, private readonly int $firstToAct = 1)
    {
    }

    public function newGame(Seed $seed, Scoring $scoring = Scoring::Ffb, ?int $target = null, ?Seat $dealer = null): Step
    {
        $this->drawn[] = ['deal', -1, 'deal', $seed->toHex()];

        return new Step(new GameState(['phase' => 'bidding', 'turn' => $this->firstToAct, 'left' => $this->actions, 'round' => 1, 'hands' => [['7S'], ['8S'], ['9S'], ['TS']]]), [['type' => 'dealt', 'dealer' => 0]]);
    }

    public function apply(GameState $state, Seat $seat, Action $action, Seed $seed): Step
    {
        $data = $state->toEngineArray();
        $over = 'roundEnd' === $data['phase'];
        if ($over !== (ActionKind::Next === $action->kind) || (!$over && $seat !== $state->turn())) {
            throw new EngineRefused('not now');
        }
        $this->drawn[] = ['apply', $seat->value, $action->kind->value, $seed->toHex()];
        $this->reasons[] = $action->reason;
        \assert(\is_int($data['left']) && \is_int($data['round']));
        if ($over) {
            return new Step(new GameState(['phase' => 'bidding', 'turn' => $this->firstToAct, 'left' => $this->actions, 'round' => $data['round'] + 1] + $data), [['type' => 'dealt', 'dealer' => 1]]);
        }
        $left = $data['left'] - 1;

        return new Step(
            new GameState(['phase' => 0 === $left ? 'roundEnd' : 'bidding', 'turn' => $seat->next()->value, 'left' => $left] + $data),
            [['type' => 'acted', 'seat' => $seat->value]],
        );
    }

    public function view(GameState $state, Seat $seat): SeatView
    {
        $data = $state->toEngineArray();
        $over = 'roundEnd' === $data['phase'];
        $play = static fn (int $by): array => ['seat' => $by, 'card' => '7S'];

        return new SeatView([
            'seat' => $seat->value,
            'hand' => ['7S'],
            'phase' => $data['phase'],
            'turn' => $data['turn'],
            'round' => $data['round'],
            'scoring' => 'ffb',
            'dealer' => 0,
            'scores' => [0, 0],
            'bids' => [['seat' => 1, 'type' => 'pass']],
            'tricks' => $over ? array_fill(0, 8, ['leader' => 1, 'winner' => 1, 'plays' => array_map($play, [1, 2, 3, 0])]) : [],
        ]);
    }

    public function legalMoves(GameState $state, Seat $seat): LegalMoves
    {
        return new LegalMoves($state->turn() === $seat ? [['type' => 'pass']] : [], [], 'roundEnd' === $state->phase()->value);
    }

    public function robotMove(GameState $state, Level $level, Seed $seed, Lang $lang = Lang::Fr): RobotMove
    {
        $this->drawn[] = ['robot', $state->turn()->value, $level->value.' '.$lang->value, $seed->toHex()];

        return new RobotMove($state->turn(), Action::bid(Bid::pass(), 'I hold the jack of spades'));
    }

    public function replay(DealRecord $record): ReplayedDeal
    {
        throw new \LogicException('Nothing replays a deal here.');
    }
}
