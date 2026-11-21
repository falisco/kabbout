<?php

declare(strict_types=1);

namespace Kabbout\Assistant\UserInterface\Http;

use Kabbout\Api\Domain\Envelope;
use Kabbout\Api\Domain\FromThePage;
use Kabbout\Assistant\Application\Coach\AskedTooOften;
use Kabbout\Assistant\Application\Coach\CoachResting;
use Kabbout\Assistant\Application\Conventions\ConventionsModelFailed;
use Kabbout\Assistant\Application\Conventions\InterpretConventions;
use Kabbout\Assistant\Application\Conventions\InvalidSentence;
use Kabbout\Assistant\Application\Conventions\NotUnderstood;
use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Assistant\Domain\InvalidAiSettings;
use Kabbout\Assistant\Infrastructure\EnvAiConfiguration;
use Kabbout\Game\Domain\Lang;
use Kabbout\Identity\Domain\NetworkAddress;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * POST /api/conventions/interpret: a player's sentence about how they and their partner bid, answered
 * with the conventions card it describes and the pieces of the sentence the card has no place for.
 * The body holds the sentence and the language of the screen. A sentence that describes no card is
 * answered 422. The card is not read back in words here: the page does that, from the card.
 *
 * Only the game's own page may ask (FromThePage), as for the coach, and for the same reason: each
 * sentence is a call to a model, counted against the visitor's pace and the day's questions.
 *
 * This is only the HTTP door: what a sentence becomes is InterpretConventions' business.
 */
final readonly class ConventionsController
{
    /** a sentence is 300 characters at most; nothing a player sends here is larger than this */
    private const int MAX_BODY_BYTES = 4 * 1024;
    private const int JSON_DEPTH = 2;

    public function __construct(private InterpretConventions $interpret, private AiConfiguration $configuration)
    {
    }

    #[Route('/api/conventions/interpret', name: 'conventions_interpret', methods: ['POST'])]
    #[IsCsrfTokenValid(FromThePage::CHECK, tokenKey: FromThePage::HEADER, tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            [$sentence, $lang] = self::asked($request);
        } catch (\InvalidArgumentException $e) {
            return self::error(Response::HTTP_BAD_REQUEST, 'bad_request', $e->getMessage());
        }
        try {
            if (null === $this->configuration->settings()) {
                return self::error(Response::HTTP_SERVICE_UNAVAILABLE, 'ai_off', \sprintf('Sentences are not read: set %s in .env and start again. The ready cards need no key.', EnvAiConfiguration::VARIABLE));
            }
            $interpreted = ($this->interpret)(NetworkAddress::of($request->getClientIp()), $sentence, $lang);
        } catch (InvalidSentence $e) {
            return self::error(Response::HTTP_BAD_REQUEST, 'bad_request', $e->getMessage());
        } catch (InvalidAiSettings $e) {
            return self::error(Response::HTTP_SERVICE_UNAVAILABLE, 'invalid_ai_settings', $e->getMessage());
        } catch (AskedTooOften $tooOften) {
            throw new TooManyRequestsHttpException($tooOften->retryAfter);
        } catch (CoachResting) {
            return self::error(Response::HTTP_TOO_MANY_REQUESTS, 'daily_quota', 'The day\'s questions are spent.');
        } catch (NotUnderstood) {
            return self::error(Response::HTTP_UNPROCESSABLE_ENTITY, 'not_understood', 'The sentence describes no conventions card.');
        } catch (ConventionsModelFailed) {
            return self::error(Response::HTTP_SERVICE_UNAVAILABLE, 'model_unavailable', 'The sentence could not be read right now. The ready cards need no model.');
        }

        return new JsonResponse(Envelope::answer(['card' => $interpreted->card->toArray(), 'unplaced' => $interpreted->unplaced]));
    }

    /**
     * @return array{string, Lang} the sentence and the language of the screen
     *
     * @throws \InvalidArgumentException a body that is not the expected one, said in words for whoever writes a client
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
            throw new \InvalidArgumentException('The body is not JSON, or holds more than a sentence and a language.');
        }
        $lang = \is_array($body) && \is_string($body['lang'] ?? null) ? Lang::tryFrom($body['lang']) : null;
        if (!\is_array($body) || !\is_string($body['sentence'] ?? null) || null === $lang) {
            throw new \InvalidArgumentException('The body holds a sentence and a language (fr, en or ar).');
        }

        return [$body['sentence'], $lang];
    }

    private static function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(Envelope::error($code, $message), $status);
    }
}
