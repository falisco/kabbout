<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure\Bridge\Mapping;

use Kabbout\Game\Domain\Action;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\GameState;
use Kabbout\Game\Domain\LegalMoves;
use Kabbout\Game\Domain\ReplayedDeal;
use Kabbout\Game\Domain\RobotMove;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\SeatView;
use Kabbout\Game\Domain\Step;
use Kabbout\Game\Infrastructure\Bridge\Transport\BridgeResponse;

/**
 * Reads what the bridge answered into what the domain speaks of. An answer that does not have the
 * expected shape is our own failure (EngineUnavailable), never passed on half-read.
 */
final class AnswerMapper
{
    private const int OK = 200;
    private const int REFUSED = 422;

    /**
     * The body of an accepted call. A refusal of the engine becomes EngineRefused, with its words and
     * its place; anything else is a failure, said with its status: a request the bridge did not
     * understand is a bug on our side.
     *
     * @return array<string, mixed>
     */
    public static function accepted(BridgeResponse $response, string $path): array
    {
        if (self::OK === $response->status) {
            return Json::object($response->body, 'answer');
        }
        $error = \is_array($response->body) && \is_array($response->body['error'] ?? null) ? $response->body['error'] : [];
        $message = \is_string($error['message'] ?? null) ? $error['message'] : 'no message';
        if (self::REFUSED !== $response->status) {
            throw new EngineUnavailable(\sprintf('The bridge answered %d to %s: %s', $response->status, $path, $message));
        }
        $at = null;
        if (isset($error['at'])) {
            $at = array_map(static fn (mixed $place): int => Json::int($place, 'place'), Json::object($error['at'], 'place'));
        }

        throw new EngineRefused($message, $at);
    }

    /** @param array<string, mixed> $answer */
    public static function step(array $answer): Step
    {
        try {
            $state = new GameState(Json::object($answer['state'] ?? null, 'state'));
        } catch (\InvalidArgumentException $e) {
            throw new EngineUnavailable('The bridge answered a state that is not one: '.$e->getMessage(), previous: $e);
        }

        return new Step($state, Json::objects($answer['events'] ?? null, 'events'));
    }

    /** @param array<string, mixed> $answer */
    public static function view(array $answer): SeatView
    {
        try {
            return new SeatView(Json::object($answer['view'] ?? null, 'view'));
        } catch (\InvalidArgumentException $e) {
            throw new EngineUnavailable('The bridge answered a view that is not one: '.$e->getMessage(), previous: $e);
        }
    }

    /** @param array<string, mixed> $answer */
    public static function legalMoves(array $answer): LegalMoves
    {
        return new LegalMoves(
            Json::objects($answer['bids'] ?? null, 'bids'),
            array_map(
                static fn (string $code): Card => Card::tryFromCode($code) ?? throw new EngineUnavailable('The bridge answered a card that is not one.'),
                Json::strings($answer['cards'] ?? null, 'cards'),
            ),
            Json::bool($answer['next'] ?? null, 'word on the next deal'),
        );
    }

    /** @param array<string, mixed> $answer */
    public static function robotMove(array $answer): RobotMove
    {
        $seat = Seat::tryFrom(Json::int($answer['seat'] ?? null, 'seat')) ?? throw new EngineUnavailable('The bridge answered an unknown seat.');

        return new RobotMove($seat, Action::fromEngine(Json::object($answer['action'] ?? null, 'action')));
    }

    /** @param array<string, mixed> $answer */
    public static function replayedDeal(array $answer): ReplayedDeal
    {
        return new ReplayedDeal(
            array_map(static fn (mixed $hand): array => Json::strings($hand, 'cards'), Json::list($answer['hands'] ?? null, 'hands')),
            Json::object($answer['contract'] ?? null, 'contract'),
            Json::objects($answer['tricks'] ?? null, 'tricks'),
            Json::object($answer['result'] ?? null, 'result'),
        );
    }
}
