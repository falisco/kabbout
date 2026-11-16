<?php

declare(strict_types=1);

namespace Kabbout\Game\Infrastructure\Bridge\Mapping;

use Kabbout\Game\Domain\Analysis\DealSummary;
use Kabbout\Game\Domain\Analysis\Ending;
use Kabbout\Game\Domain\Analysis\ExpertChoice;
use Kabbout\Game\Domain\Analysis\Side;
use Kabbout\Game\Domain\Analysis\Swing;
use Kabbout\Game\Domain\Analysis\Swings;
use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\Analysis\TrickReview;
use Kabbout\Game\Domain\Analysis\WhatIf;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Scoring;
use Kabbout\Game\Domain\Seat;

/** Reads the bridge's analysis answers into the domain's words; a shape that is not the expected one is EngineUnavailable. */
final class AnalysisMapper
{
    /** @param array<string, mixed> $a */
    public static function summary(array $a): DealSummary
    {
        $contract = Json::object($a['contract'] ?? null, 'contract');
        $belote = $a['belote'] ?? null;
        if (null !== $belote && 'us' !== $belote && 'them' !== $belote) {
            throw new EngineUnavailable('The bridge answered no belote holder.');
        }
        $scoring = Scoring::tryFrom(self::string($a['scoring'] ?? null, 'count')) ?? throw new EngineUnavailable('The bridge answered an unknown count.');

        return new DealSummary(
            self::seat($a['seat'] ?? null),
            $scoring,
            self::seat($a['dealer'] ?? null),
            array_map(self::bid(...), Json::objects($a['bids'] ?? null, 'bids')),
            [
                'seat' => self::seat($contract['seat'] ?? null)->value,
                'value' => Json::int($contract['value'] ?? null, 'contract value'),
                'suit' => self::string($contract['suit'] ?? null, 'trump suit'),
                'mult' => Json::int($contract['mult'] ?? null, 'contract multiplier'),
            ],
            Json::bool($a['taking'] ?? null, 'taking side'),
            Json::bool($a['made'] ?? null, 'word on the contract'),
            $belote,
            self::side($a['us'] ?? null),
            self::side($a['them'] ?? null),
        );
    }

    /** @param array<string, mixed> $a */
    public static function trick(array $a): TrickReview
    {
        return new TrickReview(
            self::trickNumber($a['trick'] ?? null),
            self::seat($a['leader'] ?? null),
            self::plays($a['plays'] ?? null),
            self::seat($a['winner'] ?? null),
            Json::int($a['points'] ?? null, 'trick points'),
            Json::bool($a['won'] ?? null, 'trick winner'),
            self::card($a['played'] ?? null),
        );
    }

    /** @param array<string, mixed> $a */
    public static function expertChoice(array $a): ExpertChoice
    {
        $cards = array_map(
            static fn (array $c): array => ['card' => self::card($c['card'] ?? null)->code(), 'average' => Json::int($c['average'] ?? null, 'average')],
            Json::objects($a['cards'] ?? null, 'card averages'),
        );

        return new ExpertChoice(
            self::trickNumber($a['trick'] ?? null),
            array_map(static fn (string $code): string => self::card($code)->code(), Json::strings($a['hand'] ?? null, 'cards')),
            self::plays($a['onTable'] ?? null),
            self::card($a['played'] ?? null),
            self::card($a['choice'] ?? null),
            Json::bool($a['forced'] ?? null, 'word on the choice'),
            Json::int($a['worlds'] ?? null, 'number of deals'),
            $cards,
            Json::int($a['loss'] ?? null, 'loss'),
        );
    }

    /** @param array<string, mixed> $a */
    public static function whatIf(array $a): WhatIf
    {
        return new WhatIf(
            self::trickNumber($a['trick'] ?? null),
            self::card($a['played'] ?? null),
            self::card($a['card'] ?? null),
            self::ending($a['asPlayed'] ?? null),
            self::ending($a['instead'] ?? null),
            Json::int($a['difference'] ?? null, 'difference'),
        );
    }

    /** @param array<string, mixed> $a */
    public static function swings(array $a): Swings
    {
        $swings = array_map(
            static fn (array $s): Swing => new Swing(
                self::trickNumber($s['trick'] ?? null),
                self::card($s['played'] ?? null),
                self::card($s['choice'] ?? null),
                Json::int($s['loss'] ?? null, 'loss'),
            ),
            Json::objects($a['swings'] ?? null, 'swings'),
        );

        return new Swings($swings, Json::int($a['choices'] ?? null, 'number of choices'));
    }

    private static function ending(mixed $value): Ending
    {
        $e = Json::object($value, 'ending');

        return new Ending(Json::bool($e['made'] ?? null, 'word on the contract'), self::side($e['us'] ?? null), self::side($e['them'] ?? null));
    }

    private static function side(mixed $value): Side
    {
        $s = Json::object($value, 'side');

        return new Side(Json::int($s['tricks'] ?? null, 'tricks'), Json::int($s['cardPoints'] ?? null, 'card points'), Json::int($s['gained'] ?? null, 'score'));
    }

    /**
     * @param array<string, mixed> $bid
     *
     * @return array<string, int|string>
     */
    private static function bid(array $bid): array
    {
        $kept = [];
        foreach ($bid as $field => $value) {
            if (!\is_int($value) && !\is_string($value)) {
                throw new EngineUnavailable('The bridge answered a bid that is not one.');
            }
            $kept[$field] = $value;
        }

        return $kept;
    }

    /** @return list<array{seat: int, card: string}> */
    private static function plays(mixed $value): array
    {
        return array_map(
            static fn (array $play): array => ['seat' => self::seat($play['seat'] ?? null)->value, 'card' => self::card($play['card'] ?? null)->code()],
            Json::objects($value, 'plays'),
        );
    }

    private static function seat(mixed $value): Seat
    {
        return Seat::tryFrom(Json::int($value, 'seat')) ?? throw new EngineUnavailable('The bridge answered an unknown seat.');
    }

    private static function card(mixed $value): Card
    {
        return Card::tryFromCode(self::string($value, 'card')) ?? throw new EngineUnavailable('The bridge answered a card that is not one.');
    }

    private static function trickNumber(mixed $value): TrickNumber
    {
        return TrickNumber::tryFrom(Json::int($value, 'trick')) ?? throw new EngineUnavailable('The bridge answered a trick that is not one.');
    }

    private static function string(mixed $value, string $what): string
    {
        return \is_string($value) ? $value : throw new EngineUnavailable(\sprintf('The bridge answered no %s.', $what));
    }
}
