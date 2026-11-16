<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure\Bridge;

use Kabbout\Game\Domain\Analysis\DealSummary;
use Kabbout\Game\Domain\Analysis\ExpertChoice;
use Kabbout\Game\Domain\Analysis\Swings;
use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\Analysis\TrickReview;
use Kabbout\Game\Domain\Analysis\WhatIf;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\DealAnalysis;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Infrastructure\Bridge\Mapping\AnalysisMapper;
use Kabbout\Game\Infrastructure\Bridge\Mapping\AnswerMapper;
use Kabbout\Game\Infrastructure\Bridge\Transport\BridgeTransport;

/** The analysis of a deal, asked through the bridge: which call each question becomes, nothing more. */
final readonly class BridgeDealAnalysis implements DealAnalysis
{
    public function __construct(private BridgeTransport $transport)
    {
    }

    public function summary(DealRecord $record, Seat $seat): DealSummary
    {
        return AnalysisMapper::summary($this->ask('/analysis/summary', $record, $seat));
    }

    public function trick(DealRecord $record, Seat $seat, TrickNumber $trick): TrickReview
    {
        return AnalysisMapper::trick($this->ask('/analysis/trick', $record, $seat, ['trick' => $trick->value]));
    }

    public function expertChoice(DealRecord $record, Seat $seat, TrickNumber $trick): ExpertChoice
    {
        return AnalysisMapper::expertChoice($this->ask('/analysis/expert-choice', $record, $seat, ['trick' => $trick->value]));
    }

    public function whatIf(DealRecord $record, Seat $seat, TrickNumber $trick, Card $card): WhatIf
    {
        return AnalysisMapper::whatIf($this->ask('/analysis/what-if', $record, $seat, ['trick' => $trick->value, 'card' => $card->code()]));
    }

    public function swings(DealRecord $record, Seat $seat): Swings
    {
        return AnalysisMapper::swings($this->ask('/analysis/swings', $record, $seat));
    }

    /**
     * @param array<string, int|string> $more
     *
     * @return array<string, mixed>
     */
    private function ask(string $path, DealRecord $record, Seat $seat, array $more = []): array
    {
        return AnswerMapper::accepted($this->transport->post($path, ['record' => $record->toArray(), 'seat' => $seat->value, ...$more]), $path);
    }
}
