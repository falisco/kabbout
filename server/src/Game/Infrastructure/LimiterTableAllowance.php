<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure;

use Kabbout\Game\Application\Table\PlayedTooFast;
use Kabbout\Game\Application\Table\TableAllowance;
use Kabbout\Identity\Domain\NetworkAddress;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The pace of the calls to the tables and the count of the tables opened: two Symfony rate limiters
 * (config/packages/framework.yaml), each counted per address. Neither is counted under a lock: two
 * calls at the same instant may be counted as one, and a pace of hundreds a minute is not wrong for it.
 */
final readonly class LimiterTableAllowance implements TableAllowance
{
    public function __construct(private RateLimiterFactoryInterface $tablesLimiter, private RateLimiterFactoryInterface $tablesOpenedLimiter)
    {
    }

    public function pace(NetworkAddress $from): void
    {
        self::count($this->tablesLimiter, $from);
    }

    public function open(NetworkAddress $from): void
    {
        self::count($this->tablesOpenedLimiter, $from);
    }

    private static function count(RateLimiterFactoryInterface $limiter, NetworkAddress $from): void
    {
        $limit = $limiter->create($from->key)->consume();
        if (!$limit->isAccepted()) {
            throw new PlayedTooFast(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }
    }
}
