<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure\Bridge;

use Kabbout\Game\Domain\Analysis\BidReview;
use Kabbout\Game\Domain\Analysis\DealSummary;
use Kabbout\Game\Domain\Analysis\ExpertChoice;
use Kabbout\Game\Domain\Analysis\Swings;
use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\Analysis\TrickReview;
use Kabbout\Game\Domain\Analysis\WhatIf;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\DealAnalysis;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Infrastructure\Bridge\Mapping\AnalysisMapper;
use Kabbout\Game\Infrastructure\Bridge\Mapping\AnswerMapper;
use Kabbout\Game\Infrastructure\Bridge\Transport\BridgeTransport;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The analysis of a deal, asked through the bridge: which call each question becomes, nothing more.
 *
 * The bridge answers the same question the same way, and an analysis is seconds of its one thread: an
 * answer is kept for the rest of the request, so the coach's tools and its fallback do not have the
 * same deal worked out twice.
 */
final class BridgeDealAnalysis implements DealAnalysis, ResetInterface
{
    /** a question to the coach asks a dozen things of one deal: more than this is another deal's, and is let go */
    private const int KEPT = 32;

    /** @var array<string, array<string, mixed>> the answers of this request, by the call that got them */
    private array $answers = [];

    public function __construct(private readonly BridgeTransport $transport)
    {
    }

    public function reset(): void
    {
        $this->answers = [];
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

    public function bids(DealRecord $record, Seat $seat, Lang $lang, ?ConventionCard $card = null): BidReview
    {
        return AnalysisMapper::bids($this->ask('/analysis/bids', $record, $seat, ['lang' => $lang->value, ...(null === $card ? [] : ['conventions' => $card->toArray()])]));
    }

    /**
     * @param array<string, int|string|array<string, int|string>> $more
     *
     * @return array<string, mixed>
     */
    private function ask(string $path, DealRecord $record, Seat $seat, array $more = []): array
    {
        $body = ['record' => $record->toArray(), 'seat' => $seat->value, ...$more];
        $asked = $path.' '.hash('sha256', json_encode($body, \JSON_THROW_ON_ERROR));
        if (!isset($this->answers[$asked])) {
            if (\count($this->answers) >= self::KEPT) {
                $this->answers = [];
            }
            // a refusal is an exception and is not kept: only what the bridge accepted is
            $this->answers[$asked] = AnswerMapper::accepted($this->transport->post($path, $body), $path);
        }

        return $this->answers[$asked];
    }
}
