<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure\Bridge;

use Kabbout\Game\Domain\Action;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Engine;
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
use Kabbout\Game\Infrastructure\Bridge\Mapping\AnswerMapper;
use Kabbout\Game\Infrastructure\Bridge\Transport\BridgeTransport;

/**
 * The engine, asked through the bridge. This class only says which call each question becomes.
 * How a call travels is the transport's business (Transport/); how an answer is read is the mapper's (Mapping/).
 */
final readonly class BridgeEngine implements Engine
{
    public function __construct(private BridgeTransport $transport)
    {
    }

    public function newGame(Seed $seed, Scoring $scoring = Scoring::Ffb, ?int $target = null, ?Seat $dealer = null): Step
    {
        $body = ['seed' => $seed->toHex(), 'scoring' => $scoring->value];
        if (null !== $target) {
            $body['target'] = $target;
        }
        if (null !== $dealer) {
            $body['dealer'] = $dealer->value;
        }

        return AnswerMapper::step($this->ask('/games', $body));
    }

    public function apply(GameState $state, Seat $seat, Action $action, Seed $seed): Step
    {
        return AnswerMapper::step($this->ask('/games/apply', [
            'state' => $state->toEngineArray(),
            'seat' => $seat->value,
            'action' => $action->toEngineArray(),
            'seed' => $seed->toHex(),
        ]));
    }

    public function view(GameState $state, Seat $seat): SeatView
    {
        return AnswerMapper::view($this->ask('/games/view', ['state' => $state->toEngineArray(), 'seat' => $seat->value]));
    }

    public function legalMoves(GameState $state, Seat $seat): LegalMoves
    {
        return AnswerMapper::legalMoves($this->ask('/games/legal', ['state' => $state->toEngineArray(), 'seat' => $seat->value]));
    }

    public function robotMove(GameState $state, Level $level, Seed $seed, Lang $lang = Lang::Fr): RobotMove
    {
        return AnswerMapper::robotMove($this->ask('/games/robot', [
            'state' => $state->toEngineArray(),
            'level' => $level->value,
            'lang' => $lang->value,
            'seed' => $seed->toHex(),
        ]));
    }

    public function replay(DealRecord $record): ReplayedDeal
    {
        return AnswerMapper::replayedDeal($this->ask('/deals/replay', ['record' => $record->toArray()]));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function ask(string $path, array $body): array
    {
        return AnswerMapper::accepted($this->transport->post($path, $body), $path);
    }
}
