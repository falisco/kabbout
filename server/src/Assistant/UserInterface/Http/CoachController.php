<?php

declare(strict_types=1);

namespace Kabbout\Assistant\UserInterface\Http;

use Kabbout\Assistant\Application\Coach\AnswerVisitor;
use Kabbout\Assistant\Application\Coach\AskedTooOften;
use Kabbout\Assistant\Application\Coach\CoachResting;
use Kabbout\Assistant\Application\Coach\DealSpent;
use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Assistant\Domain\InvalidAiSettings;
use Kabbout\Assistant\Infrastructure\EnvAiConfiguration;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Exception\InvalidDealRecord;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Seat;
use Kabbout\Identity\Application\CurrentPlayer;
use Kabbout\Identity\Domain\NetworkAddress;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/coach: a question about a deal just played. The body holds the record of the deal (as the
 * table saw it), the seat of the player, the language and the question. The record is not taken at its
 * word: the engine plays it again and a deal the rules do not allow is answered 422, with the place
 * where it goes wrong. The answer names the tricks and the cards it speaks of, for the screen to show
 * them, and says how many questions about this deal are left (`left`).
 *
 * This is only the HTTP door. It says who asks: the address of the request, for what is counted, and
 * the player Security recognised behind it, if any, for what is remembered. What may be asked, what is
 * remembered and what is answered are AnswerVisitor's business, shared with every other door.
 */
final readonly class CoachController
{
    private const int VERSION = 1;
    /** a record is about 2 kB; nothing a player sends is larger than this */
    private const int MAX_BODY_BYTES = 16 * 1024;
    private const int JSON_DEPTH = 8;

    public function __construct(private AnswerVisitor $answerVisitor, private AiConfiguration $configuration, private CurrentPlayer $current)
    {
    }

    #[Route('/api/coach', name: 'coach', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            [$record, $seat, $lang, $question] = self::asked($request);
            if (null === $this->configuration->settings()) {
                return self::error(Response::HTTP_SERVICE_UNAVAILABLE, 'ai_off', \sprintf('The coach is off: set %s in .env and start again.', EnvAiConfiguration::VARIABLE));
            }
            $answered = ($this->answerVisitor)(NetworkAddress::of($request->getClientIp()), $this->current->player()?->id, $record, $seat, $lang, $question);
        } catch (InvalidDealRecord|\InvalidArgumentException $e) {
            return self::error(Response::HTTP_BAD_REQUEST, 'bad_request', $e->getMessage());
        } catch (InvalidAiSettings $e) {
            return self::error(Response::HTTP_SERVICE_UNAVAILABLE, 'invalid_ai_settings', $e->getMessage());
        } catch (AskedTooOften $tooOften) {
            throw new TooManyRequestsHttpException($tooOften->retryAfter);
        } catch (CoachResting) {
            return self::error(Response::HTTP_TOO_MANY_REQUESTS, 'daily_quota', 'The coach has answered all it may today.');
        } catch (DealSpent) {
            return self::error(Response::HTTP_TOO_MANY_REQUESTS, 'deal_quota', 'No question left about this deal.', ['left' => 0]);
        } catch (EngineRefused $refused) {
            return self::error(Response::HTTP_UNPROCESSABLE_ENTITY, 'refused', $refused->getMessage(), null === $refused->at ? [] : ['at' => $refused->at]);
        } catch (EngineUnavailable) {
            return self::error(Response::HTTP_SERVICE_UNAVAILABLE, 'engine_unavailable', 'The rules engine cannot be asked right now.');
        }
        $answer = $answered->answer;

        return new JsonResponse(
            [
                'version' => self::VERSION,
                'answer' => ['text' => $answer->text, 'source' => $answer->source->value, 'tricks' => $answer->tricks, 'cards' => $answer->cards],
                'left' => $answered->left,
            ],
            headers: ['Cache-Control' => 'no-store'],
        );
    }

    /**
     * @return array{DealRecord, Seat, Lang, string} the deal, the seat, the language and the question
     *
     * @throws \InvalidArgumentException a body that is not the expected one, said in words for whoever writes a client
     * @throws InvalidDealRecord
     */
    private static function asked(Request $request): array
    {
        $content = $request->getContent();
        if (\strlen($content) > self::MAX_BODY_BYTES) {
            throw new \InvalidArgumentException(\sprintf('The body is larger than %d bytes.', self::MAX_BODY_BYTES));
        }
        try {
            $body = json_decode($content, true, self::JSON_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('The body is not JSON.');
        }
        if (!\is_array($body) || !\is_array($body['record'] ?? null)) {
            throw new \InvalidArgumentException('The body holds a record, a seat, a language and a question.');
        }
        $seat = \is_int($body['seat'] ?? null) ? Seat::tryFrom($body['seat']) : null;
        $lang = \is_string($body['lang'] ?? null) ? Lang::tryFrom($body['lang']) : null;
        if (null === $seat || null === $lang) {
            throw new \InvalidArgumentException('The seat is 0 to 3 and the language is fr, en or ar.');
        }

        return [DealRecord::fromArray($body['record']), $seat, $lang, \is_string($body['question'] ?? null) ? $body['question'] : ''];
    }

    /** @param array<string, mixed> $more */
    private static function error(int $status, string $code, string $message, array $more = []): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message, ...$more]], $status, ['Cache-Control' => 'no-store']);
    }
}
