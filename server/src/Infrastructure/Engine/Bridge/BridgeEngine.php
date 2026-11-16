<?php

declare(strict_types=1);

namespace Kabbout\Infrastructure\Engine\Bridge;

use Kabbout\Domain\Engine\Engine;
use Kabbout\Domain\Engine\Enum\Lang;
use Kabbout\Domain\Engine\Enum\Level;
use Kabbout\Domain\Engine\Enum\Scoring;
use Kabbout\Domain\Engine\Enum\Seat;
use Kabbout\Domain\Engine\Model\Action;
use Kabbout\Domain\Engine\Model\DealRecord;
use Kabbout\Domain\Engine\Model\GameState;
use Kabbout\Domain\Engine\Model\LegalMoves;
use Kabbout\Domain\Engine\Model\ReplayedDeal;
use Kabbout\Domain\Engine\Model\RobotMove;
use Kabbout\Domain\Engine\Model\SeatView;
use Kabbout\Domain\Engine\Model\Seed;
use Kabbout\Domain\Engine\Model\Step;
use Kabbout\Infrastructure\Engine\Bridge\Mapping\AnswerMapper;
use Kabbout\Infrastructure\Engine\Bridge\Transport\BridgeTransport;

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
