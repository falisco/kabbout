<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Application;

use Kabbout\Game\Application\AskExpertChoice;
use Kabbout\Game\Application\RankSwings;
use Kabbout\Game\Application\ReviewBids;
use Kabbout\Game\Application\ReviewTrick;
use Kabbout\Game\Application\SummariseDeal;
use Kabbout\Game\Application\TryWhatIf;
use Kabbout\Game\Domain\Analysis\Ending;
use Kabbout\Game\Domain\Analysis\Swing;
use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Infrastructure\Bridge\BridgeDealAnalysis;
use Kabbout\Game\Infrastructure\Bridge\Mapping\AnalysisMapper;
use Kabbout\Game\Infrastructure\Bridge\Mapping\Json;
use Kabbout\Tests\Game\Infrastructure\Bridge\EngineContractTestCase;
use Kabbout\Tests\Support\RecordedBridgeTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The capabilities every way in shares, over the deal recorded in the contract, analysed for seat 0. */
final class DealCapabilitiesTest extends TestCase
{
    private BridgeDealAnalysis $analysis;
    private DealRecord $record;

    protected function setUp(): void
    {
        $this->analysis = new BridgeDealAnalysis(RecordedBridgeTransport::fromContractFile(EngineContractTestCase::CASES));
        $this->record = DealRecord::fromArray(Json::object(self::recorded('/analysis/summary')['request']['record'] ?? null, 'record'));
    }

    public function testTheSummarySaysWhoTookWhatAndWhatEachTeamMade(): void
    {
        $summary = (new SummariseDeal($this->analysis))($this->record, Seat::First);

        self::assertSame(Seat::First, $summary->seat);
        self::assertSame(8, $summary->us->tricks + $summary->them->tricks);
        self::assertSame(162, $summary->us->cardPoints + $summary->them->cardPoints);
        self::assertSame(Seat::from($summary->contract['seat'])->team() === Seat::First->team(), $summary->taking);
    }

    public function testATrickIsFourCardsWithTheOneTheSeatPlayed(): void
    {
        $trick = (new ReviewTrick($this->analysis))($this->record, Seat::First, TrickNumber::of(1));

        self::assertCount(4, $trick->plays);
        self::assertContains(['seat' => 0, 'card' => $trick->played->code()], $trick->plays);
        self::assertSame($trick->winner->team() === Seat::First->team(), $trick->won);
    }

    public function testTheSwingsAreTheExpertsChoicesThatDifferTheCostliestFirst(): void
    {
        $swings = (new RankSwings($this->analysis))($this->record, Seat::First);
        $choice = new AskExpertChoice($this->analysis);

        self::assertNotNull($swings->worst());
        $previous = \PHP_INT_MAX;
        foreach ($swings->swings as $swing) {
            self::assertGreaterThan(0, $swing->loss);
            self::assertLessThanOrEqual($previous, $swing->loss);
            $previous = $swing->loss;
            $asked = $choice($this->record, Seat::First, $swing->trick);
            self::assertSame([$swing->played->code(), $swing->choice->code(), $swing->loss], [$asked->played->code(), $asked->choice->code(), $asked->loss]);
            self::assertFalse($asked->forced);
            self::assertContains($asked->played->code(), $asked->hand);
        }
        // a card the expert would also have played is not a swing
        for ($n = TrickNumber::FIRST; $n <= TrickNumber::LAST; ++$n) {
            $asked = $choice($this->record, Seat::First, TrickNumber::of($n));
            $listed = [] !== array_filter($swings->swings, static fn (Swing $swing): bool => $swing->trick->value === $n);
            self::assertSame($asked->loss > 0, $listed);
        }
    }

    public function testAWhatIfGivesBothEndingsAndRefusesACardTheSeatDidNotHold(): void
    {
        $asked = self::recorded('/analysis/what-if')['request'];
        $whatIf = (new TryWhatIf($this->analysis))($this->record, Seat::First, TrickNumber::of(Json::int($asked['trick'] ?? null, 'trick')), Card::fromCode(\is_string($asked['card'] ?? null) ? $asked['card'] : ''));

        $margin = static fn (Ending $ending): int => $ending->us->gained - $ending->them->gained;
        self::assertSame($whatIf->difference, $margin($whatIf->instead) - $margin($whatIf->asPlayed));
        self::assertNotSame($whatIf->played->code(), $whatIf->card->code());

        $refused = self::recorded('/analysis/what-if', 422)['request'];
        $this->expectException(EngineRefused::class);
        (new TryWhatIf($this->analysis))($this->record, Seat::First, TrickNumber::of(1), Card::fromCode(\is_string($refused['card'] ?? null) ? $refused['card'] : ''));
    }

    public function testTheAuctionIsTheSeatsBidsNextToTheExpertsWithItsReasons(): void
    {
        $review = (new ReviewBids($this->analysis))($this->record, Seat::First, Lang::Fr);

        self::assertCount(8, $review->hand);
        self::assertSame(['S', 'H', 'D', 'C'], array_column($review->suits, 'suit'));
        self::assertNotSame([], $review->turns);
        foreach ($review->turns as $i => $turn) {
            self::assertSame($i + 1, $turn['turn']);
            [$said, $expert] = [$turn['bid'], $turn['expert']];
            ksort($said);
            ksort($expert);
            self::assertSame($said === $expert, $turn['same']);
            self::assertNotSame('', $turn['why']);
        }
        // the reasons follow the language asked for, the bids do not
        $arabic = (new ReviewBids($this->analysis))($this->record, Seat::First, Lang::Ar);
        self::assertSame(array_column($review->turns, 'expert'), array_column($arabic->turns, 'expert'));
        self::assertNotSame(array_column($review->turns, 'why'), array_column($arabic->turns, 'why'));
    }

    public function testATrickNumberIsOneToEight(): void
    {
        self::assertNull(TrickNumber::tryFrom(0));
        self::assertNull(TrickNumber::tryFrom(9));
        self::assertSame(8, TrickNumber::of(8)->value);
        $this->expectException(\InvalidArgumentException::class);
        TrickNumber::of(9);
    }

    /**
     * @param callable(array<string, mixed>): mixed $read
     * @param array<string, mixed>                  $answer
     */
    #[DataProvider('brokenAnswers')]
    public function testAnAnswerThatIsNotTheExpectedOneIsNeverHalfRead(callable $read, array $answer): void
    {
        $this->expectException(EngineUnavailable::class);
        $read($answer);
    }

    /** @return iterable<string, array{callable(array<string, mixed>): mixed, array<string, mixed>}> */
    public static function brokenAnswers(): iterable
    {
        $summary = self::recorded('/analysis/summary')['response'];
        $choice = self::recorded('/analysis/expert-choice')['response'];
        $swings = self::recorded('/analysis/swings')['response'];
        yield 'a belote for nobody' => [AnalysisMapper::summary(...), ['belote' => 'nobody'] + $summary];
        yield 'an unknown count' => [AnalysisMapper::summary(...), ['scoring' => 'bridge'] + $summary];
        yield 'a count that is no word' => [AnalysisMapper::summary(...), ['scoring' => 1] + $summary];
        yield 'a fifth seat' => [AnalysisMapper::summary(...), ['dealer' => 4] + $summary];
        yield 'a bid holding a list' => [AnalysisMapper::summary(...), ['bids' => [['seat' => 0, 'type' => []]]] + $summary];
        yield 'a ninth trick' => [AnalysisMapper::expertChoice(...), ['trick' => 9] + $choice];
        yield 'a card that is none' => [AnalysisMapper::expertChoice(...), ['choice' => '1S'] + $choice];
        $reading = $choice['reading'];
        self::assertIsArray($reading);
        yield 'a table read in a fifth suit' => [AnalysisMapper::expertChoice(...), ['reading' => ['trump' => 'X'] + $reading] + $choice];
        yield 'a table read without its trumps' => [AnalysisMapper::expertChoice(...), ['reading' => ['trumps' => null] + $reading] + $choice];
        yield 'a choice without its table' => [AnalysisMapper::expertChoice(...), ['reading' => null] + $choice];
        $bids = self::recorded('/analysis/bids')['response'];
        yield 'an auction without its turns' => [AnalysisMapper::bids(...), ['turns' => null] + $bids];
        yield 'a turn of an auction without its reason' => [AnalysisMapper::bids(...), ['turns' => [['turn' => 1, 'heard' => [], 'bid' => ['type' => 'pass'], 'expert' => ['type' => 'pass'], 'same' => true]]] + $bids];
        yield 'a bid standing at no seat' => [AnalysisMapper::bids(...), ['turns' => [['turn' => 1, 'heard' => [], 'standing' => ['value' => 80, 'suit' => 'H'], 'bid' => ['type' => 'pass'], 'expert' => ['type' => 'pass'], 'why' => 'x', 'same' => true]]] + $bids];
        yield 'a hand worth something in a fifth suit' => [AnalysisMapper::bids(...), ['suits' => [['suit' => 'X', 'value' => 80, 'why' => '']]] + $bids];
        yield 'swings without their count' => [AnalysisMapper::swings(...), ['choices' => null] + $swings];
        yield 'an empty what-if' => [AnalysisMapper::whatIf(...), []];
        yield 'an empty trick' => [AnalysisMapper::trick(...), []];
    }

    /** @return array{request: array<string, mixed>, response: array<string, mixed>} the first call of the contract to that path with that status */
    private static function recorded(string $path, int $status = 200): array
    {
        $cases = json_decode((string) file_get_contents(EngineContractTestCase::CASES), true, flags: \JSON_THROW_ON_ERROR);
        foreach (Json::objects($cases, 'calls') as $case) {
            if ($path === $case['path'] && $status === $case['status']) {
                return ['request' => Json::object($case['request'], 'request'), 'response' => Json::object($case['response'], 'response')];
            }
        }
        self::fail('The contract holds no such call: '.$path);
    }
}
