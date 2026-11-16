<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Application\Coach;

use Kabbout\Assistant\Application\Coach\AnswerSource;
use Kabbout\Assistant\Application\Coach\AskCoach;
use Kabbout\Assistant\Application\Coach\CoachAnswer;
use Kabbout\Assistant\Application\Coach\CoachBrief;
use Kabbout\Assistant\Application\Coach\CoachModelFailed;
use Kabbout\Assistant\Application\Coach\PlainSummary;
use Kabbout\Assistant\Domain\Grounding\Facts;
use Kabbout\Assistant\Domain\Grounding\GroundingGuard;
use Kabbout\Game\Domain\Analysis\Swing;
use Kabbout\Game\Domain\Analysis\Swings;
use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Infrastructure\Bridge\Mapping\Json;
use Kabbout\Tests\Support\RecordedDeal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The coach over the deal of the contract, where seat 0's costliest decision is the ten of clubs in
 * trick 4 (the expert plays the ace of diamonds), then the seven of spades in trick 6, by too little to be called a mistake.
 */
final class AskCoachTest extends TestCase
{
    private const string INVENTED = 'Au pli 4, vous avez joué le 10 de trèfle ; le roi de pique rapportait 999 points de plus.';

    public function testAGroundedAnswerPassesUntouched(): void
    {
        $model = new ScriptedCoachModel([self::grounded()], [self::swings()]);

        $answer = $this->ask($model);

        self::assertSame(self::grounded(), $answer->text);
        self::assertSame(AnswerSource::Model, $answer->source);
        self::assertSame([4], $answer->tricks);
        self::assertSame(['TC', 'AD'], $answer->cards);
        self::assertSame([null], $model->calls);
    }

    public function testAnInventedNumberGetsTheModelOneMoreTryWithWhatItInvented(): void
    {
        $model = new ScriptedCoachModel([self::INVENTED, self::grounded()], [self::swings()]);

        $answer = $this->ask($model);

        self::assertSame(self::grounded(), $answer->text);
        self::assertSame(AnswerSource::Model, $answer->source);
        self::assertCount(2, $model->calls);
        self::assertSame(self::INVENTED, $model->calls[1]?->answer);
        self::assertSame(['999', 'KS'], $model->calls[1]->ungrounded);
    }

    public function testASecondInventionAndThePlayerReadsThePlainSummary(): void
    {
        $model = new ScriptedCoachModel([self::INVENTED, 'Le pli 4 coûte 998 points.'], [self::swings()]);

        $answer = $this->ask($model);

        self::assertSame(AnswerSource::Summary, $answer->source);
        self::assertCount(2, $model->calls);
        self::assertStringNotContainsString('998', $answer->text);
        self::assertStringContainsString('Au pli 4, vous avez joué le 10 de trèfle ; l’expert aurait joué l’as de carreau, qui rapporte en moyenne '.self::loss().' points de plus.', $answer->text);
    }

    /** @param list<string|CoachModelFailed> $answers */
    #[DataProvider('noAnswers')]
    public function testWhateverHappensToTheModelThePlayerGetsTheEnginesAnswer(array $answers, bool $toolsCalled): void
    {
        $model = new ScriptedCoachModel($answers, $toolsCalled ? [self::swings()] : []);

        $answer = $this->ask($model);

        self::assertSame(AnswerSource::Summary, $answer->source);
        self::assertSame([4], $answer->tricks);
        self::assertSame(['TC', 'AD'], $answer->cards);
        self::assertCount(1, $model->calls);
    }

    /** @return iterable<string, array{list<string|CoachModelFailed>, bool}> */
    public static function noAnswers(): iterable
    {
        yield 'the platform is down' => [[new CoachModelFailed('down')], true];
        yield 'an empty answer' => [["  \n"], true];
        yield 'an answer that never ends' => [[str_repeat('Au pli 4. ', 200)], true];
        yield 'an answer with no tool behind it' => [['Vous avez très bien joué, bravo.'], false];
    }

    public function testADealTheRulesDoNotAllowIsRefusedBeforeTheModelIsAsked(): void
    {
        $transport = RecordedDeal::transport();
        $tampered = RecordedDeal::call('/analysis/swings', 422);
        $record = DealRecord::fromArray(Json::object($tampered['request']['record'] ?? null, 'record'));
        $transport->record('/analysis/summary', ['record' => $record->toArray(), 'seat' => 0], 422, $tampered['response']);
        $model = new ScriptedCoachModel([]);
        $ask = new AskCoach($model, RecordedDeal::summarise($transport), RecordedDeal::rankSwings($transport));

        try {
            $ask(new CoachBrief($record, Seat::First, Lang::Fr, 'Qu’ai-je mal joué ?'));
            self::fail('The engine should have refused.');
        } catch (EngineRefused $refused) {
            self::assertSame(['trick' => 4, 'seat' => 3], $refused->at);
        }
        self::assertSame([], $model->calls);
    }

    /** @param non-empty-string $start */
    #[DataProvider('languages')]
    public function testThePlainSummaryIsMadeOfTheEnginesNumbersInEveryLanguage(Lang $lang, string $start): void
    {
        $summary = RecordedDeal::summarise()(RecordedDeal::record(), Seat::First);
        $swings = RecordedDeal::rankSwings()(RecordedDeal::record(), Seat::First);

        $answer = PlainSummary::of($summary, $swings, $lang);

        self::assertStringStartsWith($start, $answer->text);
        self::assertSame([4], $answer->tricks);
        $facts = new Facts();
        $facts->add($summary->toArray());
        $facts->add($swings->toArray());
        // the summary is held to the rule it stands in for
        self::assertSame([], GroundingGuard::ungrounded($answer->text, $facts));

        // a card the expert only leans away from is not blamed: with nothing but those, the play was sound
        $slight = new Swings([new Swing(TrickNumber::of(6), Card::fromCode('7S'), Card::fromCode('KC'), 29, false, null)], 6);
        self::assertSame(PlainSummary::of($summary, new Swings([], 6), $lang)->text, PlainSummary::of($summary, $slight, $lang)->text);
        // several mistakes: the costliest in full, the next ones by their trick
        $several = new Swings([...$swings->mistakes(), new Swing(TrickNumber::of(6), Card::fromCode('7S'), Card::fromCode('KC'), 31, true, null), ...$slight->swings], 6);
        $listed = PlainSummary::of($summary, $several, $lang);
        self::assertSame([4, 6], $listed->tricks);
        self::assertStringContainsString(match ($lang) {
            Lang::Fr => 'Autres décisions coûteuses : pli 6.',
            Lang::En => 'Other costly decisions: trick 6.',
            Lang::Ar => 'قرارات أخرى غالية: البلي 6.',
        }, $listed->text);

        $clean = PlainSummary::of($summary, new Swings([], 6), $lang);
        self::assertSame([[], []], [$clean->tricks, $clean->cards]);
        self::assertSame([], GroundingGuard::ungrounded($clean->text, $facts));
        self::assertNotSame($answer->text, $clean->text);
    }

    /** @return iterable<string, array{Lang, non-empty-string}> */
    public static function languages(): iterable
    {
        yield 'fr' => [Lang::Fr, 'Au pli 4, vous avez joué le 10 de trèfle ; l’expert aurait joué l’as de carreau, qui rapporte en moyenne '.self::loss().' points de plus. Contrat de 80 à carreau réussi'];
        yield 'en' => [Lang::En, 'In trick 4 you played the 10 of clubs; the expert would have played the ace of diamonds, worth '.self::loss().' more points on average. Contract of 80 in diamonds, made'];
        yield 'ar' => [Lang::Ar, 'في البلي 4 لعبت 10 سباتة؛ الخبير كان يلعب اللاص كارو، وهي تجيب '.self::loss().' نقطة أكثر في المعدّل. الكونترا 80 كارو'];
    }

    public function testAQuestionIsOneLineOfPlainTextOfAReasonableLength(): void
    {
        $brief = new CoachBrief(RecordedDeal::record(), Seat::First, Lang::En, "  What did\n\nI do\twrong?\u{0000}\u{202E} ");
        self::assertSame('What did I do wrong?', $brief->question);
        self::assertSame(300, mb_strlen((new CoachBrief(RecordedDeal::record(), Seat::First, Lang::Ar, str_repeat('ش', 300)))->question));

        foreach (['', " \n ", str_repeat('a', 301)] as $question) {
            try {
                new CoachBrief(RecordedDeal::record(), Seat::First, Lang::En, $question);
                self::fail('The question should have been refused.');
            } catch (\InvalidArgumentException $e) {
                self::assertSame('A question to the coach is 1 to 300 characters.', $e->getMessage());
            }
        }
    }

    private function ask(ScriptedCoachModel $model): CoachAnswer
    {
        return (new AskCoach($model, RecordedDeal::summarise(), RecordedDeal::rankSwings()))(new CoachBrief(RecordedDeal::record(), Seat::First, Lang::Fr, 'Qu’ai-je mal joué ?'));
    }

    private static function grounded(): string
    {
        return 'Au pli 4, vous avez joué le 10 de trèfle ; l’as de carreau rapportait '.self::loss().' points de plus en moyenne.';
    }

    /** what the ten of clubs cost in trick 4, as the engine counted it when the contract was recorded */
    private static function loss(): int
    {
        return Json::int(Json::objects(self::swings()['swings'], 'swings')[0]['loss'] ?? null, 'loss');
    }

    /** @return array<string, mixed> what the swings tool returns for the deal */
    private static function swings(): array
    {
        return RecordedDeal::call('/analysis/swings')['response'];
    }
}
