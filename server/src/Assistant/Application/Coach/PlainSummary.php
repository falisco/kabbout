<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

use Kabbout\Assistant\Domain\Grounding\CardVocabulary;
use Kabbout\Game\Domain\Analysis\DealSummary;
use Kabbout\Game\Domain\Analysis\Swing;
use Kabbout\Game\Domain\Analysis\Swings;
use Kabbout\Game\Domain\Lang;

/**
 * The coach's answer when the model cannot be trusted with it or cannot be reached: the costliest
 * decision and the result of the deal, in sentences written in advance. Nothing here is generated.
 */
final class PlainSummary
{
    /** how many other costly tricks are listed after the first */
    private const int OTHERS = 2;

    public static function of(DealSummary $summary, Swings $swings, Lang $lang): CoachAnswer
    {
        // only clear losses are mistakes: a card the expert merely leans away from is not blamed here
        $mistakes = $swings->mistakes();
        $worst = $mistakes[0] ?? null;
        $others = \array_slice($mistakes, 1, self::OTHERS);
        $sentences = [null === $worst ? self::nothing($lang) : self::worst($worst, $lang)];
        if ([] !== $others) {
            $sentences[] = self::others($others, $lang);
        }
        $sentences[] = self::result($summary, $lang);

        $tricks = array_map(static fn (Swing $swing): int => $swing->trick->value, null === $worst ? [] : [$worst, ...$others]);
        sort($tricks);

        return new CoachAnswer(
            implode(' ', $sentences),
            AnswerSource::Summary,
            $tricks,
            null === $worst ? [] : [$worst->played->code(), $worst->choice->code()],
        );
    }

    private static function worst(Swing $swing, Lang $lang): string
    {
        $played = CardVocabulary::inSentence($swing->played->code(), $lang->value);
        $choice = CardVocabulary::inSentence($swing->choice->code(), $lang->value);

        return match ($lang) {
            Lang::Fr => \sprintf('Au pli %d, vous avez joué %s ; l’expert aurait joué %s, qui rapporte en moyenne %d points de plus.', $swing->trick->value, $played, $choice, $swing->loss),
            Lang::En => \sprintf('In trick %d you played %s; the expert would have played %s, worth %d more points on average.', $swing->trick->value, $played, $choice, $swing->loss),
            Lang::Ar => \sprintf('في البلي %d لعبت %s؛ الخبير كان يلعب %s، وهي تجيب %d نقطة أكثر في المعدّل.', $swing->trick->value, $played, $choice, $swing->loss),
        };
    }

    /** @param non-empty-list<Swing> $others */
    private static function others(array $others, Lang $lang): string
    {
        [$word, $comma] = match ($lang) {
            Lang::Fr => ['pli', ', '],
            Lang::En => ['trick', ', '],
            Lang::Ar => ['البلي', '، '],
        };
        $list = implode($comma, array_map(static fn (Swing $swing): string => $word.' '.$swing->trick->value, $others));

        return match ($lang) {
            Lang::Fr => 'Autres décisions coûteuses : '.$list.'.',
            Lang::En => 'Other costly decisions: '.$list.'.',
            Lang::Ar => 'قرارات أخرى غالية: '.$list.'.',
        };
    }

    private static function nothing(Lang $lang): string
    {
        return match ($lang) {
            Lang::Fr => 'À chaque pli, vous avez joué une carte aussi bonne que celle de l’expert, ou presque : rien à reprocher à votre jeu de la carte.',
            Lang::En => 'In every trick you played a card as good as the expert’s, or nearly: nothing to blame in your card play.',
            Lang::Ar => 'في كل بلي لعبت كارطة قد كارطة الخبير ولا تقريب: ما فمّا حتى غلطة في لعبك.',
        };
    }

    private static function result(DealSummary $summary, Lang $lang): string
    {
        $suit = CardVocabulary::suit($summary->contract['suit'], $lang->value);
        $value = $summary->contract['value'];

        return match ($lang) {
            Lang::Fr => \sprintf('Contrat de %d à %s %s : votre camp marque %d, l’autre %d.', $value, $suit, $summary->made ? 'réussi' : 'chuté', $summary->us->gained, $summary->them->gained),
            Lang::En => \sprintf('Contract of %d in %s, %s: your side scores %d, the other %d.', $value, $suit, $summary->made ? 'made' : 'defeated', $summary->us->gained, $summary->them->gained),
            Lang::Ar => \sprintf('الكونترا %d %s %s: فريقك يسجّل %d والفريق الآخر %d.', $value, $suit, $summary->made ? 'تعدّات' : 'طاحت', $summary->us->gained, $summary->them->gained),
        };
    }
}
