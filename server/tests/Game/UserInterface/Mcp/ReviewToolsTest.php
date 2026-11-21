<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\UserInterface\Mcp;

use Kabbout\Game\Domain\Analysis\BidReview;
use Kabbout\Game\Domain\Analysis\DealSummary;
use Kabbout\Game\Domain\Analysis\ExpertChoice;
use Kabbout\Game\Domain\Analysis\Swings;
use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\Analysis\TrickReview;
use Kabbout\Game\Domain\Analysis\WhatIf;
use Kabbout\Game\Domain\BidType;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\DealAnalysis;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Engine;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\UserInterface\Mcp\ReviewTools;
use Kabbout\Game\UserInterface\Mcp\TableTools;
use Kabbout\Tests\Game\Application\Table\TurnsEngine;
use Mcp\Exception\ToolCallException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The review of a deal's auction at an MCP table is asked with the conventions the table was opened
 * with: the expert the player is compared with bids as their partner expected. The engine and the
 * analysis are stand-ins that write down what they are asked.
 */
final class ReviewToolsTest extends KernelTestCase
{
    protected function setUp(): void
    {
        $_SERVER['KABBOUT_TABLES_LIMIT'] = $_SERVER['KABBOUT_TABLES_OPENED'] = $_SERVER['KABBOUT_GUESTS_LIMIT'] = '100000';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['KABBOUT_TABLES_LIMIT'], $_SERVER['KABBOUT_TABLES_OPENED'], $_SERVER['KABBOUT_GUESTS_LIMIT']);
        parent::tearDown();
    }

    public function testTheAuctionIsReviewedByTheCardTheTableWasOpenedWithAndByNoneWhenItWasOpenedWithNone(): void
    {
        self::assertSame(ConventionCard::READY['bold'], $this->cardTheReviewIsAskedWith('bold'));
        self::assertNull($this->cardTheReviewIsAskedWith(null));
    }

    /** @return array<string, int|string>|null the card `expert_bids` hands the analysis, for a table opened with `$conventions` */
    private function cardTheReviewIsAskedWith(?string $conventions): ?array
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $analysis = new class implements DealAnalysis {
            /** @var list<array<string, int|string>|null> */
            public array $cards = [];

            public function bids(DealRecord $record, Seat $seat, Lang $lang, ?ConventionCard $card = null): BidReview
            {
                $this->cards[] = $card?->toArray();

                throw new EngineRefused('asked, and that is all this stand-in does');
            }

            public function summary(DealRecord $record, Seat $seat): DealSummary
            {
                throw new \LogicException('not asked for here');
            }

            public function trick(DealRecord $record, Seat $seat, TrickNumber $trick): TrickReview
            {
                throw new \LogicException('not asked for here');
            }

            public function expertChoice(DealRecord $record, Seat $seat, TrickNumber $trick): ExpertChoice
            {
                throw new \LogicException('not asked for here');
            }

            public function whatIf(DealRecord $record, Seat $seat, TrickNumber $trick, Card $card): WhatIf
            {
                throw new \LogicException('not asked for here');
            }

            public function swings(DealRecord $record, Seat $seat): Swings
            {
                throw new \LogicException('not asked for here');
            }
        };
        self::getContainer()->set(Engine::class, new TurnsEngine());
        self::getContainer()->set(DealAnalysis::class, $analysis);
        $tables = self::getContainer()->get(TableTools::class);
        $reviews = self::getContainer()->get(ReviewTools::class);
        self::assertInstanceOf(TableTools::class, $tables);
        self::assertInstanceOf(ReviewTools::class, $reviews);

        // a deal played to its end at the stand-in engine: the player passes until it is over
        $opened = $tables->newGame(Level::Easy, Seat::First, conventions: $conventions)->structuredContent;
        $table = \is_array($opened) && \is_string($opened['table'] ?? null) ? $opened['table'] : self::fail('no table');
        for ($turns = 0; $turns < 8; ++$turns) {
            try {
                $reviews->expertBids($table);
            } catch (ToolCallException $refused) {
                if ([] !== $analysis->cards) {
                    break;
                }
                // no deal is over yet: the review says so, and the player plays on
                self::assertStringNotContainsString('asked, and that is all', $refused->getMessage());
                $tables->bid($table, BidType::Pass);
            }
        }
        self::assertCount(1, $analysis->cards, 'The review never reached the analysis.');

        return $analysis->cards[0];
    }
}
