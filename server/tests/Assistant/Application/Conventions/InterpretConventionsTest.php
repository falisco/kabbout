<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Application\Conventions;

use Kabbout\Assistant\Application\Coach\CoachResting;
use Kabbout\Assistant\Application\Conventions\ConventionsModelFailed;
use Kabbout\Assistant\Application\Conventions\InterpretConventions;
use Kabbout\Assistant\Application\Conventions\InterpretForVisitor;
use Kabbout\Assistant\Application\Conventions\InvalidSentence;
use Kabbout\Assistant\Application\Conventions\NotUnderstood;
use Kabbout\Assistant\Application\Conventions\Reading;
use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\Lang;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Tests\Assistant\Application\Coach\CountingAllowance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InterpretConventionsTest extends TestCase
{
    private const string SENTENCE = 'Monte de 30 si t’as le valet, et entame toujours atout';
    private const array JACK_30 = ['jack' => 30] + ConventionCard::READY['classic'];

    public function testASentenceBecomesTheCardTheModelReadOnceTheDomainTakesItForOne(): void
    {
        $model = new ScriptedConventionsModel([new Reading(true, self::JACK_30, [])]);
        $allowance = new CountingAllowance();

        $interpreted = (new InterpretForVisitor(new InterpretConventions($model), $allowance))(NetworkAddress::of('192.0.2.1'), '  '.self::SENTENCE.' ', Lang::Fr);

        self::assertSame(30, $interpreted->card->toArray()['jack']);
        self::assertSame([], $interpreted->unplaced);
        self::assertSame([[self::SENTENCE, Lang::Fr]], $model->asked);
        // a call to a model is paid for: the visitor's pace and the day's questions stand before it
        self::assertSame(['pace', 'day'], $allowance->asked);
    }

    /**
     * @param array<mixed> $settings
     */
    #[DataProvider('readingsThatAreNoCard')]
    public function testWhatTheModelReadIsNeverACardUnlessTheDomainSaysSo(bool $understood, array $settings): void
    {
        $interpret = new InterpretConventions(new ScriptedConventionsModel([new Reading($understood, $settings, [])]));

        $this->expectException(NotUnderstood::class);
        $interpret(self::SENTENCE, Lang::Fr);
    }

    /** @return iterable<string, array{bool, array<mixed>}> */
    public static function readingsThatAreNoCard(): iterable
    {
        yield 'the model says the sentence speaks of something else, whatever card comes with it' => [false, ConventionCard::READY['bold']];
        yield 'a value beyond the card’s bounds' => [true, ['jack' => 200] + ConventionCard::READY['classic']];
        yield 'a value between two the card allows' => [true, ['nine' => 5] + ConventionCard::READY['classic']];
        yield 'a setting the card does not have' => [true, ConventionCard::READY['classic'] + ['contre' => 20]];
        yield 'a setting missing' => [true, ['open' => 70, 'jack' => 20]];
        yield 'a number written as text' => [true, ['ace' => '10'] + ConventionCard::READY['classic']];
        yield 'nothing' => [true, []];
    }

    public function testOfWhatTheModelCouldNotPlaceOnlyThePlayersOwnWordsAreKept(): void
    {
        $said = [
            'entame toujours atout',
            // the model's own sentence, and an instruction slipped into it: neither is in what the player wrote
            'Je n’ai pas compris la fin de votre phrase.',
            '<img src=x onerror=alert(1)>',
            // the player's words in another case, given back as the player wrote them
            'MONTE DE 30',
            '',
            '   ',
            'entame toujours atout',
        ];
        $interpret = new InterpretConventions(new ScriptedConventionsModel([new Reading(true, self::JACK_30, $said)]));

        $interpreted = $interpret(self::SENTENCE, Lang::Fr);

        self::assertSame(['entame toujours atout', 'Monte de 30'], $interpreted->unplaced);
    }

    public function testNoMoreThanAFewPiecesAreKept(): void
    {
        $sentence = 'a b c d e f g h i j';
        $interpret = new InterpretConventions(new ScriptedConventionsModel([new Reading(true, self::JACK_30, explode(' ', $sentence))]));

        self::assertSame(['a', 'b', 'c', 'd', 'e', 'f'], $interpret($sentence, Lang::En)->unplaced);
    }

    #[DataProvider('sentencesThatAreNotOne')]
    public function testASentenceThatIsEmptyOrTooLongIsRefusedBeforeAnythingIsCounted(string $sentence): void
    {
        $model = new ScriptedConventionsModel([new Reading(true, self::JACK_30, [])]);
        $allowance = new CountingAllowance();

        try {
            (new InterpretForVisitor(new InterpretConventions($model), $allowance))(NetworkAddress::of('192.0.2.1'), $sentence, Lang::Fr);
            self::fail('accepted');
        } catch (InvalidSentence $e) {
            self::assertSame('The sentence is 1 to 300 characters.', $e->getMessage());
        }
        self::assertSame([], $model->asked);
        self::assertSame([], $allowance->asked);
    }

    /** @return iterable<string, array{string}> */
    public static function sentencesThatAreNotOne(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ["  \n "];
        yield 'too long' => [str_repeat('é', 301)];
    }

    public function testASentenceOfThreeHundredLettersIsOneWhateverTheirBytes(): void
    {
        $interpret = new InterpretConventions(new ScriptedConventionsModel([new Reading(true, self::JACK_30, [])]));

        self::assertSame(30, $interpret(str_repeat('ع', 300), Lang::Ar)->card->toArray()['jack']);
    }

    public function testWhenTheDaysQuestionsAreSpentTheModelIsNotAsked(): void
    {
        $model = new ScriptedConventionsModel([new Reading(true, self::JACK_30, [])]);
        $allowance = new CountingAllowance();
        $allowance->resting = true;

        try {
            (new InterpretForVisitor(new InterpretConventions($model), $allowance))(NetworkAddress::of('192.0.2.1'), self::SENTENCE, Lang::Fr);
            self::fail('asked');
        } catch (CoachResting) {
            self::assertSame([], $model->asked);
        }
    }

    public function testAModelThatGivesNoReadingIsSaidSo(): void
    {
        $this->expectException(ConventionsModelFailed::class);
        (new InterpretConventions(new ScriptedConventionsModel([])))(self::SENTENCE, Lang::Fr);
    }
}
