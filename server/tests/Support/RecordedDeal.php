<?php

declare(strict_types=1);

namespace Kabbout\Tests\Support;

use Kabbout\Game\Application\Analysis\RankSwings;
use Kabbout\Game\Application\Analysis\SummariseDeal;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Infrastructure\Bridge\BridgeDealAnalysis;
use Kabbout\Game\Infrastructure\Bridge\Mapping\Json;

/**
 * The deal of the contract file (bridge/contract/cases.json), with the analysis recorded for seat 0:
 * the tests of the coach stand on it, with no bridge running.
 */
final class RecordedDeal
{
    public const string CASES = __DIR__.'/../../../bridge/contract/cases.json';

    public static function transport(): RecordedBridgeTransport
    {
        return RecordedBridgeTransport::fromContractFile(self::CASES);
    }

    public static function analysis(?RecordedBridgeTransport $transport = null): BridgeDealAnalysis
    {
        return new BridgeDealAnalysis($transport ?? self::transport());
    }

    public static function summarise(?RecordedBridgeTransport $transport = null): SummariseDeal
    {
        return new SummariseDeal(self::analysis($transport));
    }

    public static function rankSwings(?RecordedBridgeTransport $transport = null): RankSwings
    {
        return new RankSwings(self::analysis($transport));
    }

    public static function record(): DealRecord
    {
        return DealRecord::fromArray(Json::object(self::call('/analysis/summary')['request']['record'] ?? null, 'record'));
    }

    /** @return array{request: array<string, mixed>, response: array<string, mixed>} the first call of the contract to that path with that status */
    public static function call(string $path, int $status = 200): array
    {
        $cases = json_decode((string) file_get_contents(self::CASES), true, flags: \JSON_THROW_ON_ERROR);
        foreach (Json::objects($cases, 'calls') as $case) {
            if ($path === $case['path'] && $status === $case['status']) {
                return ['request' => Json::object($case['request'], 'request'), 'response' => Json::object($case['response'], 'response')];
            }
        }
        throw new \LogicException('The contract holds no such call: '.$path);
    }
}
