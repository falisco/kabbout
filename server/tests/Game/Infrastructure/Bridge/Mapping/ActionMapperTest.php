<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Infrastructure\Bridge\Mapping;

use Kabbout\Game\Domain\Action;
use Kabbout\Game\Domain\Bid;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Suit;
use Kabbout\Game\Infrastructure\Bridge\Mapping\ActionMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActionMapperTest extends TestCase
{
    /** @return iterable<string, array{Action, array<string, mixed>}> */
    public static function actions(): iterable
    {
        yield 'a pass' => [Action::bid(Bid::pass()), ['kind' => 'bid', 'bid' => ['type' => 'pass']]];
        yield 'a contre' => [Action::bid(Bid::contre()), ['kind' => 'bid', 'bid' => ['type' => 'contre']]];
        yield 'a surcontre' => [Action::bid(Bid::surcontre()), ['kind' => 'bid', 'bid' => ['type' => 'surcontre']]];
        yield 'a raise' => [Action::bid(Bid::raise(110, Suit::Clubs)), ['kind' => 'bid', 'bid' => ['type' => 'bid', 'value' => 110, 'suit' => 'C']]];
        yield 'a robot\'s bid' => [Action::bid(Bid::pass(), 'nothing to say'), ['kind' => 'bid', 'bid' => ['type' => 'pass'], 'why' => 'nothing to say']];
        yield 'a card' => [Action::play(Card::fromCode('TH')), ['kind' => 'play', 'card' => 'TH']];
        yield 'the next deal' => [Action::next(), ['kind' => 'next']];
    }

    /** @param array<string, mixed> $written */
    #[DataProvider('actions')]
    public function testAnActionIsWrittenAsTheEngineReadsItAndReadBackTheSame(Action $action, array $written): void
    {
        self::assertSame($written, ActionMapper::toArray($action));
        self::assertEquals($action, ActionMapper::fromArray($written));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function notActions(): iterable
    {
        yield 'nothing' => [[]];
        yield 'an unknown kind' => [['kind' => 'dance']];
        yield 'a kind that is not a word' => [['kind' => 1]];
        yield 'a play with no card' => [['kind' => 'play']];
        yield 'a play of a card that is not one' => [['kind' => 'play', 'card' => '1S']];
        yield 'a bid with no bid' => [['kind' => 'bid']];
        yield 'a bid of an unknown type' => [['kind' => 'bid', 'bid' => ['type' => 'double']]];
        yield 'a raise with no value' => [['kind' => 'bid', 'bid' => ['type' => 'bid', 'suit' => 'S']]];
        yield 'a raise in a suit that does not exist' => [['kind' => 'bid', 'bid' => ['type' => 'bid', 'value' => 80, 'suit' => 'X']]];
        yield 'a reason that is not words' => [['kind' => 'bid', 'bid' => ['type' => 'pass'], 'why' => 3]];
    }

    /** @param array<string, mixed> $written */
    #[DataProvider('notActions')]
    public function testWhatIsNotAnActionIsRefused(array $written): void
    {
        $this->expectException(EngineUnavailable::class);
        ActionMapper::fromArray($written);
    }
}
