<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Infrastructure;

use Kabbout\Assistant\Application\Coach\CoachMemory;
use Kabbout\Assistant\Application\Coach\Exchange;
use Kabbout\Assistant\Infrastructure\CacheCoachMemory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CacheCoachMemoryTest extends TestCase
{
    public function testAConversationIsKeptInOrderAndApartFromTheOthers(): void
    {
        $memory = new CacheCoachMemory(new ArrayAdapter());
        self::assertSame([], $memory->earlier('2001:db8::1|deal'));

        $memory->remember('2001:db8::1|deal', new Exchange('Qu’ai-je mal joué ?', 'Le pli 4.'));
        $memory->remember('2001:db8::1|deal', new Exchange('Et pourquoi ?', 'L’as était maître.'));

        self::assertEquals([new Exchange('Qu’ai-je mal joué ?', 'Le pli 4.'), new Exchange('Et pourquoi ?', 'L’as était maître.')], $memory->earlier('2001:db8::1|deal'));
        self::assertSame([], $memory->earlier('2001:db8::2|deal'));
        self::assertSame([], $memory->earlier('2001:db8::1|another deal'));
    }

    public function testOnlyTheLastExchangesAreKept(): void
    {
        $cache = new ArrayAdapter();
        $memory = new CacheCoachMemory($cache);
        for ($i = 1; $i <= CoachMemory::MAX_EXCHANGES + 2; ++$i) {
            $memory->remember('thread', new Exchange("question $i", "answer $i"));
        }

        $kept = $memory->earlier('thread');
        self::assertCount(CoachMemory::MAX_EXCHANGES, $kept);
        self::assertSame('question 3', $kept[0]->question);
        self::assertSame('answer '.(CoachMemory::MAX_EXCHANGES + 2), $kept[CoachMemory::MAX_EXCHANGES - 1]->answer);
    }

    public function testWhatTheCacheHoldsThatIsNoConversationIsNoConversation(): void
    {
        $cache = new ArrayAdapter();
        $memory = new CacheCoachMemory($cache);
        $memory->remember('thread', new Exchange('q', 'a'));
        $key = array_key_first($cache->getValues());
        self::assertIsString($key);
        foreach (['not a list', [['q'], 'x', ['q', 7], ['kept', 'too']]] as $stored) {
            $cache->save($cache->getItem($key)->set($stored));
            self::assertEquals(\is_array($stored) ? [new Exchange('kept', 'too')] : [], $memory->earlier('thread'));
        }
    }
}
