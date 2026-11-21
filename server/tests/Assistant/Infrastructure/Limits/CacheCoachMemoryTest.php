<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Infrastructure\Limits;

use Kabbout\Assistant\Application\Coach\CoachMemory;
use Kabbout\Assistant\Application\Coach\Exchange;
use Kabbout\Assistant\Infrastructure\Limits\CacheCoachMemory;
use Kabbout\Identity\Domain\PlayerId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CacheCoachMemoryTest extends TestCase
{
    public function testAConversationIsKeptInOrderAndApartFromTheOthers(): void
    {
        $memory = new CacheCoachMemory(new ArrayAdapter());
        [$ana, $ben] = [PlayerId::random(), PlayerId::random()];
        self::assertSame([], $memory->earlier($ana, 'deal'));

        $memory->remember($ana, 'deal', new Exchange('Qu’ai-je mal joué ?', 'Le pli 4.'));
        $memory->remember($ana, 'deal', new Exchange('Et pourquoi ?', 'L’as était maître.'));

        self::assertEquals([new Exchange('Qu’ai-je mal joué ?', 'Le pli 4.'), new Exchange('Et pourquoi ?', 'L’as était maître.')], $memory->earlier($ana, 'deal'));
        // another player asking about the same deal, and the same player about another deal
        self::assertSame([], $memory->earlier($ben, 'deal'));
        self::assertSame([], $memory->earlier($ana, 'another deal'));
    }

    public function testOnlyTheLastExchangesAreKept(): void
    {
        $cache = new ArrayAdapter();
        $memory = new CacheCoachMemory($cache);
        $player = PlayerId::random();
        for ($i = 1; $i <= CoachMemory::MAX_EXCHANGES + 2; ++$i) {
            $memory->remember($player, 'deal', new Exchange("question $i", "answer $i"));
        }

        $kept = $memory->earlier($player, 'deal');
        self::assertCount(CoachMemory::MAX_EXCHANGES, $kept);
        self::assertSame('question 3', $kept[0]->question);
        self::assertSame('answer '.(CoachMemory::MAX_EXCHANGES + 2), $kept[CoachMemory::MAX_EXCHANGES - 1]->answer);
    }

    public function testWhatTheCacheHoldsThatIsNoConversationIsNoConversation(): void
    {
        $cache = new ArrayAdapter();
        $memory = new CacheCoachMemory($cache);
        $player = PlayerId::random();
        $memory->remember($player, 'deal', new Exchange('q', 'a'));
        $key = array_key_first($cache->getValues());
        self::assertIsString($key);
        foreach (['not a list', [['q'], 'x', ['q', 7], ['kept', 'too']]] as $stored) {
            $cache->save($cache->getItem($key)->set($stored));
            self::assertEquals(\is_array($stored) ? [new Exchange('kept', 'too')] : [], $memory->earlier($player, 'deal'));
        }
    }
}
