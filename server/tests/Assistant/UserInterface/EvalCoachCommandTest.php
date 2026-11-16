<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\UserInterface;

use Kabbout\Assistant\Application\Coach\CoachBrief;
use Kabbout\Assistant\Application\Coach\CoachModel;
use Kabbout\Assistant\Application\Coach\CoachModelFailed;
use Kabbout\Assistant\Application\Coach\Rejected;
use Kabbout\Assistant\Domain\Grounding\CardVocabulary;
use Kabbout\Assistant\Domain\Grounding\Facts;
use Kabbout\Game\Application\RankSwings;
use Kabbout\Game\Domain\Lang;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The measure of the coach, on the real evaluation set and the real engine (the bridge of BRIDGE_URL),
 * with a model whose answers are known: right in French, beside the point in English, absent in Arabic.
 */
#[Group('integration')]
final class EvalCoachCommandTest extends KernelTestCase
{
    private const string DEALS = __DIR__.'/../../../../eval/coach';

    protected function setUp(): void
    {
        $url = getenv('BRIDGE_URL');
        if (false === $url || '' === $url) {
            self::markTestSkipped('No bridge to ask: BRIDGE_URL is not set.');
        }
        $_SERVER['ANTHROPIC_API_KEY'] = 'sk-ant-a-secret-key';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['ANTHROPIC_API_KEY']);
        parent::tearDown();
    }

    public function testItCountsTheAnswersOfTheModelThatNameTheExpectedMistake(): void
    {
        $tester = self::command();
        $rankSwings = self::getContainer()->get(RankSwings::class);
        self::assertInstanceOf(RankSwings::class, $rankSwings);
        self::getContainer()->set(CoachModel::class, new readonly class($rankSwings) implements CoachModel {
            public function __construct(private RankSwings $rankSwings)
            {
            }

            public function answer(CoachBrief $brief, Facts $facts, ?Rejected $rejected = null): string
            {
                if (Lang::Ar === $brief->lang) {
                    throw new CoachModelFailed('down');
                }
                $swings = ($this->rankSwings)($brief->record, $brief->seat);
                $facts->add($swings->toArray());
                $worst = $swings->worst() ?? throw new \LogicException('Every deal of the set holds a mistake.');

                return Lang::Fr === $brief->lang
                    ? \sprintf('Au pli %d, %s était de trop.', $worst->trick->value, CardVocabulary::inSentence($worst->played->code(), 'fr'))
                    : 'Well played, on the whole.';
            }
        });

        self::assertSame(0, $tester->execute(['directory' => self::DEALS], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));

        $output = $tester->getDisplay();
        self::assertStringContainsString('Coach evaluation: 15 deals, claude-sonnet-5-5 on anthropic, '.date('Y-m-d').'.', $output);
        self::assertStringContainsString('Named the expected mistake: 5/15 (fr 5/5, en 0/5, ar 0/5). Answers written by the model: 10/15.', $output);
        self::assertSame(5, substr_count($output, ': named'));
        self::assertSame(5, substr_count($output, ': missed'));
        self::assertSame(5, substr_count($output, 'no answer from the model (plain summary shown)'));
        // with -v, what the player would have read
        self::assertStringContainsString('Well played, on the whole.', $output);
        self::assertStringNotContainsString('sk-ant-a-secret-key', $output);
    }

    public function testWithoutAKeyOrWithoutDealsThereIsNothingToMeasure(): void
    {
        $tester = self::command();
        self::assertSame(1, $tester->execute(['directory' => __DIR__]));
        self::assertStringContainsString('No deal in', $tester->getDisplay());

        self::ensureKernelShutdown();
        unset($_SERVER['ANTHROPIC_API_KEY']);
        $tester = self::command();
        self::assertSame(1, $tester->execute(['directory' => self::DEALS]));
        self::assertStringContainsString('The AI is off: set ANTHROPIC_API_KEY to measure the coach.', $tester->getDisplay());
    }

    private static function command(): CommandTester
    {
        return new CommandTester((new Application(self::bootKernel()))->find('kabbout:coach:eval'));
    }
}
