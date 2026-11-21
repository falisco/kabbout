<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\UserInterface;

use Kabbout\Assistant\Application\Conventions\ConventionsModel;
use Kabbout\Assistant\Application\Conventions\ConventionsModelFailed;
use Kabbout\Assistant\Application\Conventions\Reading;
use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\Lang;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The measure of the reading of conventions, on the real set, with a model whose readings are known:
 * right in French, the classic card whatever is said in English, nothing understood in Arabic, and no
 * answer at all in derja.
 */
final class EvalConventionsCommandTest extends KernelTestCase
{
    private const string SET = __DIR__.'/../../../../eval/conventions/sentences.json';

    protected function setUp(): void
    {
        $_SERVER['ANTHROPIC_API_KEY'] = 'sk-ant-a-secret-key';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['ANTHROPIC_API_KEY']);
        parent::tearDown();
    }

    public function testTheSetHoldsAtLeastThirtySentencesInFourWaysOfWritingEachWithACardOrNone(): void
    {
        $set = self::sentences();

        self::assertGreaterThanOrEqual(30, \count($set));
        $byWriting = array_count_values(array_column($set, 'writing'));
        self::assertSame(['fr', 'en', 'ar', 'derja'], array_keys($byWriting));
        $none = 0;
        foreach ($set as $one) {
            self::assertNotNull(Lang::tryFrom($one['lang']));
            self::assertNotSame('', trim($one['sentence']));
            self::assertLessThanOrEqual(300, mb_strlen($one['sentence']));
            if (null === $one['expected']) {
                ++$none;
            } else {
                self::assertSame($one['expected'], ConventionCard::fromArray($one['expected'])->toArray(), $one['sentence']);
            }
        }
        // some must come back as not understood, in every way of writing
        self::assertCount($none, $byWriting);
        self::assertCount(\count($set), array_unique(array_column($set, 'sentence')));
    }

    public function testItCountsTheSentencesThatGiveTheExpectedCardOverallAndForEachWayOfWriting(): void
    {
        $expected = [];
        foreach (self::sentences() as $one) {
            $expected[$one['sentence']] = [$one['writing'], $one['expected']];
        }
        $tester = self::command(new readonly class($expected) implements ConventionsModel {
            /** @param array<string, array{string, array<string, int|string>|null}> $expected */
            public function __construct(private array $expected)
            {
            }

            public function read(string $sentence, Lang $lang): Reading
            {
                [$writing, $card] = $this->expected[$sentence];

                return match ($writing) {
                    'fr' => new Reading(null !== $card, $card ?? [], []),
                    'en' => new Reading(true, ConventionCard::READY['classic'], []),
                    'ar' => new Reading(false, [], []),
                    default => throw new ConventionsModelFailed('down'),
                };
            }
        });

        self::assertSame(0, $tester->execute(['file' => self::SET]));

        $output = $tester->getDisplay();
        self::assertMatchesRegularExpression('/^Conventions evaluation: 34 sentences, \S+ on anthropic, \d{4}-\d\d-\d\d\.$/m', $output);
        // French all right; English right only where nothing was to be understood... which the classic card is not; Arabic right only there; derja never
        self::assertStringContainsString('Expected card: 11/34 (fr 10/10, en 0/8, ar 1/8, derja 0/8). Sentences the model gave no answer to: 8.', $output);
        // a wrong card is shown by the settings it gets wrong
        self::assertStringContainsString('expected {"jack":30}, got {"jack":20}', $output);
        self::assertStringContainsString('expected {"jack":30}, got not understood', $output);
        self::assertStringContainsString('expected not understood, got the classic card', $output);
        self::assertStringContainsString('no answer from the model', $output);
    }

    public function testWithNoKeyItSaysTheAiIsOffAndMeasuresNothing(): void
    {
        unset($_SERVER['ANTHROPIC_API_KEY']);
        $tester = self::command(null);

        self::assertSame(1, $tester->execute(['file' => self::SET], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('The AI is off: set ANTHROPIC_API_KEY', $tester->getErrorOutput());
    }

    public function testAFileThatIsNoSetIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::command(null)->execute(['file' => __FILE__.'.missing']);
    }

    /** @return list<array{writing: string, lang: string, sentence: string, expected: array<string, int|string>|null}> */
    private static function sentences(): array
    {
        /** @var list<array{writing: string, lang: string, sentence: string, expected: array<string, int|string>|null}> $set */
        $set = json_decode((string) file_get_contents(self::SET), true, flags: \JSON_THROW_ON_ERROR);

        return $set;
    }

    private static function command(?ConventionsModel $model): CommandTester
    {
        self::ensureKernelShutdown();
        $kernel = self::bootKernel();
        if (null !== $model) {
            self::getContainer()->set(ConventionsModel::class, $model);
        }

        return new CommandTester((new Application($kernel))->find('kabbout:conventions:eval'));
    }
}
