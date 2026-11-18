<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure;

use Kabbout\Game\Application\Table\PlayedTooFast;
use Kabbout\Game\Application\Table\TableAllowance;
use Kabbout\Identity\Domain\NetworkAddress;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/** The pace of the calls to the tables: a Symfony rate limiter (config/packages/framework.yaml), counted per address. */
final readonly class LimiterTableAllowance implements TableAllowance
{
    public function __construct(private RateLimiterFactoryInterface $tablesLimiter)
    {
    }

    public function pace(NetworkAddress $from): void
    {
        $limit = $this->tablesLimiter->create($from->key)->consume();
        if (!$limit->isAccepted()) {
            throw new PlayedTooFast(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }
    }
}
