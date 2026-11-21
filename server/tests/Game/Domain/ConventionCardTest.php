<?php

declare(strict_types=1);

namespace Kabbout\Tests\Game\Domain;

use Kabbout\Game\Domain\ConventionCard;
use Kabbout\Game\Domain\Exception\InvalidConventionCard;
use Kabbout\Game\Infrastructure\Bridge\Mapping\Json;
use PHPUnit\Framework\TestCase;

/**
 * The conventions card, held to what the engine reads as one: bridge/contract/conventions.json is written
 * from the engine and checked against it by the bridge's tests. A value the engine gains or loses, a ready
 * card that changes, and this fails until the PHP side follows.
 */
final class ConventionCardTest extends TestCase
{
    private const string CONTRACT = __DIR__.'/../../../../bridge/contract/conventions.json';

    public function testItAllowsWhatTheEngineAllowsSettingForSettingAndValueForValue(): void
    {
        self::assertSame(self::contract()['values'], ConventionCard::VALUES);
    }

    public function testTheReadyCardsAreTheEnginesAndTheClassicOneIsHowTheRobotsAlwaysBid(): void
    {
        $ready = Json::object(self::contract()['ready'], 'ready');

        self::assertSame($ready, ConventionCard::READY);
        foreach ($ready as $name => $card) {
            self::assertSame($card, ConventionCard::fromArray($card)->toArray());
            self::assertTrue(ConventionCard::fromArray($card)->equals(ConventionCard::ready($name) ?? ConventionCard::classic()));
        }
        self::assertSame($ready['classic'], ConventionCard::classic()->toArray());
        self::assertNull(ConventionCard::ready('reckless'));
        self::assertFalse(ConventionCard::classic()->equals(ConventionCard::ready('bold') ?? ConventionCard::classic()));
    }

    public function testEveryValueACardAllowsMakesACardAndTheSettingsComeBackInTheCardsOrder(): void
    {
        foreach (ConventionCard::VALUES as $setting => $values) {
            foreach ($values as $value) {
                $card = ConventionCard::fromArray([$setting => $value] + ConventionCard::READY['classic']);
                self::assertSame($value, $card->toArray()[$setting]);
            }
        }
        $shuffled = array_reverse(ConventionCard::READY['bold'], true);
        self::assertSame(array_keys(ConventionCard::VALUES), array_keys(ConventionCard::fromArray($shuffled)->toArray()));
    }

    public function testWhatTheEngineRefusesIsRefusedHereBeforeItIsAsked(): void
    {
        $refused = Json::objects(self::contract()['refused'], 'refused');
        self::assertNotSame([], $refused);
        foreach ($refused as $case) {
            $why = \is_string($case['why'] ?? null) ? $case['why'] : '';
            $card = $case['card'] ?? null;
            // what is not even an object cannot be handed to the card: whoever reads a body refuses it first
            if (!\is_array($card)) {
                continue;
            }
            try {
                ConventionCard::fromArray($card);
                self::fail('accepted: '.$why);
            } catch (InvalidConventionCard $e) {
                self::assertStringContainsString('conventions card', $e->getMessage(), $why);
            }
        }
    }

    /** @return array<string, mixed> */
    private static function contract(): array
    {
        return Json::object(json_decode((string) file_get_contents(self::CONTRACT), true, flags: \JSON_THROW_ON_ERROR), 'contract');
    }
}
