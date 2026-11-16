<?php

declare(strict_types=1);

namespace Kabbout\Assistant\UserInterface\Http;

use Kabbout\Assistant\Application\Coach\AskCoach;
use Kabbout\Assistant\Application\Coach\CoachBrief;
use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Assistant\Domain\InvalidAiSettings;
use Kabbout\Assistant\Infrastructure\EnvAiConfiguration;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Exception\InvalidDealRecord;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Seat;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/coach: a question about a deal just played. The body holds the record of the deal (as the
 * table saw it), the seat of the player, the language and the question. The record is not taken at its
 * word: the engine plays it again and a deal the rules do not allow is answered 422, with the place
 * where it goes wrong. The answer names the tricks and the cards it speaks of, for the screen to show them.
 */
final readonly class CoachController
{
    private const int VERSION = 1;
    /** a record is about 2 kB; nothing a player sends is larger than this */
    private const int MAX_BODY_BYTES = 16 * 1024;
    private const int JSON_DEPTH = 8;

    public function __construct(private AskCoach $askCoach, private AiConfiguration $configuration, private RateLimiterFactoryInterface $coachLimiter)
    {
    }

    #[Route('/api/coach', name: 'coach', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $brief = self::brief($request);
        } catch (InvalidDealRecord|\InvalidArgumentException $e) {
            return self::error(Response::HTTP_BAD_REQUEST, 'bad_request', $e->getMessage());
        }
        try {
            if (null === $this->configuration->settings()) {
                return self::error(Response::HTTP_SERVICE_UNAVAILABLE, 'ai_off', \sprintf('The coach is off: set %s in .env and start again.', EnvAiConfiguration::VARIABLE));
            }
        } catch (InvalidAiSettings $e) {
            return self::error(Response::HTTP_SERVICE_UNAVAILABLE, 'invalid_ai_settings', $e->getMessage());
        }
        // every question counts, the refused ones too: a deal is replayed and a model is paid for each
        $limit = $this->coachLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }

        try {
            $answer = ($this->askCoach)($brief);
        } catch (EngineRefused $refused) {
            return self::error(Response::HTTP_UNPROCESSABLE_ENTITY, 'refused', $refused->getMessage(), null === $refused->at ? [] : ['at' => $refused->at]);
        } catch (EngineUnavailable) {
            return self::error(Response::HTTP_SERVICE_UNAVAILABLE, 'engine_unavailable', 'The rules engine cannot be asked right now.');
        }

        return new JsonResponse(
            [
                'version' => self::VERSION,
                'answer' => ['text' => $answer->text, 'source' => $answer->source->value, 'tricks' => $answer->tricks, 'cards' => $answer->cards],
            ],
            headers: ['Cache-Control' => 'no-store'],
        );
    }

    /**
     * @throws \InvalidArgumentException a body that is not the expected one, said in words for whoever writes a client
     * @throws InvalidDealRecord
     */
    private static function brief(Request $request): CoachBrief
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

        return new CoachBrief(DealRecord::fromArray($body['record']), $seat, $lang, \is_string($body['question'] ?? null) ? $body['question'] : '');
    }

    /** @param array<string, mixed> $more */
    private static function error(int $status, string $code, string $message, array $more = []): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message, ...$more]], $status, ['Cache-Control' => 'no-store']);
    }
}
