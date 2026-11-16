<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Domain\Grounding;

use Kabbout\Assistant\Domain\Grounding\CardVocabulary;
use Kabbout\Assistant\Domain\Grounding\Facts;
use Kabbout\Assistant\Domain\Grounding\GroundingGuard;
use Kabbout\Assistant\Domain\Grounding\Reading;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GroundingGuardTest extends TestCase
{
    /**
     * @param list<string> $cards
     * @param list<int>    $numbers
     * @param list<int>    $tricks
     */
    #[DataProvider('answers')]
    public function testAnAnswerIsReadForItsCardsItsNumbersAndItsTricks(string $answer, array $cards, array $numbers, array $tricks): void
    {
        $reading = Reading::of($answer);

        self::assertSame($cards, $reading->cards);
        self::assertSame($numbers, $reading->numbers);
        self::assertSame($tricks, $reading->tricks);
    }

    /** @return iterable<string, array{string, list<string>, list<int>, list<int>}> */
    public static function answers(): iterable
    {
        yield 'French, as the screen names cards' => ['Au pli 4, vous avez joué le 10 de trèfle alors que l’as de carreau rapportait 46 points de plus.', ['TC', 'AD'], [4, 46], [4]];
        yield 'French, ranks in letters and no accents' => ['Le Dix de Trefle, puis la dame de coeur au 3e pli.', ['TC', 'QH'], [3], [3]];
        yield 'French, an ordinal in letters' => ['Au troisième pli, le valet d’atout manquait.', [], [3], [3]];
        yield 'English, numbers in letters' => ['In the fourth trick you played the ten of clubs; the Ace of Diamonds was worth 46 more.', ['TC', 'AD'], [4, 46], [4]];
        yield 'English, a trick by its number' => ['Trick number 5 went to them, trick #6 too.', [], [5, 6], [5, 6]];
        yield 'Tunisian Arabic, digits of the script' => ['في البلي ٤ لعبت 10 سباتة والخبير كان يلعب اللاص كارو: ٤٦ نقطة.', ['TC', 'AD'], [4, 46], [4]];
        yield 'Tunisian Arabic, an ordinal and the article' => ['البلي الثالث: الدامة الكور خير من فالي بيك.', ['QH', 'JS'], [3], [3]];
        yield 'Tunisian Arabic, particles joined to the card' => ['و9 سباتة كانت خير، والدامة بيك زادة، مقابل 45 للـ9 سباتة وبالروا كارو.', ['9C', 'QS', 'KD'], [45], []];
        yield 'Persian digits' => ['۱۲۰', [], [120], []];
        yield 'cards drawn with their suit' => ['Q♥ puis D♥, le 10♠, R♦ et V ♣.', ['QH', 'TS', 'KD', 'JC'], [], []];
        yield 'cards as the engine writes them' => ['You held KS and 9D, not TC.', ['KS', '9D', 'TC'], [], []];
        yield 'a rank alone is no card' => ['Your ace was safe, and the two kings behind it.', [], [2], []];
        yield 'a rank is not a number' => ['Le 7 de pique et le 8 de cœur ne valent rien.', ['7S', '8H'], [], []];
        yield 'a negative number is its size' => ['Vous perdez -80.', [], [80], []];
        yield 'nothing to check' => ['Bien joué.', [], [], []];
    }

    public function testEveryNumberAndEveryCardMustBeInAToolResult(): void
    {
        $facts = new Facts();
        self::assertTrue($facts->isEmpty());
        $facts->add(['swings' => [['trick' => 4, 'played' => 'TC', 'choice' => 'AD', 'loss' => 46]], 'choices' => 6]);
        $facts->add(['difference' => -80, 'average' => 12.4, 'note' => 'not a card', 'made' => true, 'belote' => null]);
        self::assertFalse($facts->isEmpty());

        self::assertSame([], GroundingGuard::ungrounded('Au pli 4, le 10 de trèfle coûte 46 points ; l’as de carreau était meilleur. 80 points de moins, 12 en moyenne sur 6 choix.', $facts));
        self::assertSame(['47'], GroundingGuard::ungrounded('Au pli 4, le 10 de trèfle coûte 47 points.', $facts));
        self::assertSame(['KS'], GroundingGuard::ungrounded('In trick 4 the king of spades was the card.', $facts));
        self::assertSame(['5', '90', 'trick 5', 'QH'], GroundingGuard::ungrounded('في البلي ٥ الدامة كور تجيب ٩٠، و٩٠ أخرى.', $facts));
        // the number printed on a card of the results is no invention: "your 10", "the 7, 8 and 9 of clubs"
        $facts->add(['hand' => ['7C', '8C', '9C']]);
        self::assertSame([], GroundingGuard::ungrounded('Your 10 was the card; you still held the 7, 8 and 9 of clubs.', $facts));
        self::assertSame(['QC'], GroundingGuard::ungrounded('You still held the 7, 8, 9 and queen of clubs.', $facts));
        // said in letters, an invented trick is still an invented trick
        self::assertSame(['trick 7'], GroundingGuard::ungrounded('Au septième pli, tout était joué.', $facts));
        self::assertSame(['trick 8'], GroundingGuard::ungrounded('Trick 8 was yours.', $facts));
        // counting tricks is not pointing at one
        $facts->add(['us' => ['tricks' => 6]]);
        self::assertSame([], GroundingGuard::ungrounded('Vous avez fait 6 plis, dont le pli 4.', $facts));
    }

    public function testATrickIsOnlyKnownByTheToolThatLookedAtIt(): void
    {
        $facts = new Facts();
        $facts->add(['trick' => 4, 'points' => 6, 'plays' => [['seat' => 2, 'card' => '7H']]]);

        self::assertTrue($facts->hasTrick(4));
        self::assertFalse($facts->hasTrick(6));
        self::assertFalse($facts->hasTrick(2));
        self::assertTrue($facts->hasNumber(6));
        self::assertTrue($facts->hasCard('7H'));
        self::assertFalse($facts->hasCard('7S'));
    }

    public function testACardIsNamedAsTheScreenNamesIt(): void
    {
        self::assertSame('dame de cœur', CardVocabulary::name('QH', 'fr'));
        self::assertSame('queen of hearts', CardVocabulary::name('QH', 'en'));
        self::assertSame('الدامة كور', CardVocabulary::name('QH', 'ar'));
        self::assertSame('10 de pique', CardVocabulary::name('TS', 'fr'));
        self::assertSame('سباتة', CardVocabulary::suit('C', 'ar'));
        self::assertSame(['l’as de pique', 'la dame de cœur', 'le 10 de trèfle', 'the ace of spades', 'اللاص بيك'], [CardVocabulary::inSentence('AS', 'fr'), CardVocabulary::inSentence('QH', 'fr'), CardVocabulary::inSentence('TC', 'fr'), CardVocabulary::inSentence('AS', 'en'), CardVocabulary::inSentence('AS', 'ar')]);
        // and every name is read back as the card it names
        foreach (['fr', 'en', 'ar'] as $lang) {
            foreach (str_split('789TJQKA') as $rank) {
                foreach (str_split('SHDC') as $suit) {
                    self::assertSame([$rank.$suit], Reading::of(CardVocabulary::name($rank.$suit, $lang))->cards, $lang.' '.$rank.$suit);
                }
            }
        }
    }

    /** @param callable(): string $name */
    #[DataProvider('unknownNames')]
    public function testWhatIsNoCardHasNoName(callable $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $name();
    }

    /** @return iterable<string, array{callable(): string}> */
    public static function unknownNames(): iterable
    {
        yield 'no such rank' => [static fn (): string => CardVocabulary::name('1S', 'fr')];
        yield 'no such language' => [static fn (): string => CardVocabulary::name('QH', 'de')];
        yield 'too long' => [static fn (): string => CardVocabulary::name('QHS', 'fr')];
        yield 'no such suit' => [static fn (): string => CardVocabulary::suit('X', 'fr')];
    }
}
