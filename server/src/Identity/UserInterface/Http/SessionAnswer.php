<?php

declare(strict_types=1);

namespace Kabbout\Identity\UserInterface\Http;

use Kabbout\Api\Domain\Envelope;
use Kabbout\Identity\Domain\Player;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * What both routes of /api/session answer: who is asking, by the one thing a client may know of them,
 * their name. Neither the id the server keeps them under nor their secret is ever in it.
 */
#[Exclude]
final readonly class SessionAnswer
{
    public static function of(?Player $player, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(Envelope::answer(['player' => null === $player ? null : ['name' => $player->name->toString()]]), $status);
    }
}
