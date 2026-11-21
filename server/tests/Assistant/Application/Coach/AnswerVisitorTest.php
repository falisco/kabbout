<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Application\Coach;

use Kabbout\Assistant\Application\Allowance\DaySpent;
use Kabbout\Assistant\Application\Coach\AnswerSource;
use Kabbout\Assistant\Application\Coach\AnswerVisitor;
use Kabbout\Assistant\Application\Coach\AskCoach;
use Kabbout\Assistant\Application\Coach\CoachModelFailed;
use Kabbout\Assistant\Application\Coach\DealSpent;
use Kabbout\Assistant\Application\Coach\Exchange;
use Kabbout\Game\Domain\DealRecord;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Lang;
use Kabbout\Game\Domain\Seat;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayerId;
use Kabbout\Tests\Support\RecordedBridgeTransport;
use Kabbout\Tests\Support\RecordedDeal;
use PHPUnit\Framework\TestCase;

/** What is counted against a visitor and what is remembered of a deal, whatever becomes of the question. */
final class AnswerVisitorTest extends TestCase
{
    private const string INVENTED = 'Au pli 4, le roi de pique rapportait 999 points de plus.';

    private CountingAllowance $allowance;
    private ?ListedMemory $memory = null;

    public function testAnAnswerOfTheModelIsCountedAndRememberedForTheNextQuestion(): void
    {
        $model = new ScriptedCoachModel(['Le pli 4.', 'Le pli 4 encore.'], [RecordedDeal::call('/analysis/swings')['response']]);
        $desk = $this->desk($model);
        [$address, $ana] = [NetworkAddress::of('203.0.113.1'), PlayerId::random()];

        $first = $desk($address, $ana, RecordedDeal::record(), Seat::First, Lang::Fr, 'Qu’ai-je mal joué ?');
        $desk($address, $ana, RecordedDeal::record(), Seat::First, Lang::Fr, 'Et pourquoi ?');

        self::assertSame([AnswerSource::Model, 1], [$first->answer->source, $first->left]);
        self::assertSame(['pace', 'take', 'day', 'pace', 'take', 'day'], $this->allowance->asked);
        self::assertSame([], $model->briefs[0]->earlier);
        self::assertEquals([new Exchange('Qu’ai-je mal joué ?', 'Le pli 4.')], $model->briefs[1]->earlier);
        // a conversation is a player's about a deal: another seat of the same deal is another one
        self::assertCount(1, $this->memory->threads ?? []);
    }

    public function testTwoPlayersBehindOneAddressDoNotReadEachOthersConversation(): void
    {
        $model = new ScriptedCoachModel(['Le pli 4.', 'Le pli 4 aussi.', 'Le pli 4 encore.'], [RecordedDeal::call('/analysis/swings')['response']]);
        $desk = $this->desk($model, left: 3);
        // one household, one address: the counts are shared, the conversations are not
        [$address, $ana, $ben] = [NetworkAddress::of('203.0.113.1'), PlayerId::random(), PlayerId::random()];

        $desk($address, $ana, RecordedDeal::record(), Seat::First, Lang::Fr, 'Qu’ai-je mal joué ?');
        $last = $desk($address, $ben, RecordedDeal::record(), Seat::First, Lang::Fr, 'Qu’ai-je mal joué ?');
        $desk($address, $ana, RecordedDeal::record(), Seat::First, Lang::Fr, 'Et pourquoi ?');

        self::assertSame([], $model->briefs[1]->earlier);
        self::assertEquals([new Exchange('Qu’ai-je mal joué ?', 'Le pli 4.')], $model->briefs[2]->earlier);
        self::assertCount(2, $this->memory->threads ?? []);
        self::assertSame(1, $last->left);
    }

    public function testWhoeverAsksAsNobodyIsAnsweredLikeAnybodyAndNothingIsRemembered(): void
    {
        $model = new ScriptedCoachModel(['Le pli 4.', 'Le pli 4 encore.'], [RecordedDeal::call('/analysis/swings')['response']]);
        $desk = $this->desk($model);
        $address = NetworkAddress::of('203.0.113.1');

        $first = $desk($address, null, RecordedDeal::record(), Seat::First, Lang::Fr, 'Qu’ai-je mal joué ?');
        $second = $desk($address, null, RecordedDeal::record(), Seat::First, Lang::Fr, 'Et pourquoi ?');

        self::assertSame([AnswerSource::Model, AnswerSource::Model], [$first->answer->source, $second->answer->source]);
        // each question is a first question, and each one is counted
        self::assertSame([], $model->briefs[1]->earlier);
        self::assertSame([], $this->memory->threads ?? null);
        self::assertSame([1, 0], [$first->left, $second->left]);
        self::assertSame(['pace', 'take', 'day', 'pace', 'take', 'day'], $this->allowance->asked);
    }

    public function testAModelThatGaveNothingCostsTheVisitorNoQuestionAndLeavesNoMemory(): void
    {
        $desk = $this->desk(new ScriptedCoachModel([new CoachModelFailed('down')]));

        $answered = $desk(NetworkAddress::of('203.0.113.1'), PlayerId::random(), RecordedDeal::record(), Seat::First, Lang::Fr, 'Qu’ai-je mal joué ?');

        self::assertSame([AnswerSource::Summary, 2], [$answered->answer->source, $answered->left]);
        self::assertSame(['pace', 'take', 'day', 'giveBack'], $this->allowance->asked);
        self::assertSame([], $this->memory->threads ?? null);
    }

    public function testAModelPaidTwiceForInventionsIsAQuestionAskedThoughNothingIsRemembered(): void
    {
        $desk = $this->desk(new ScriptedCoachModel([self::INVENTED, self::INVENTED], [RecordedDeal::call('/analysis/swings')['response']]));

        $answered = $desk(NetworkAddress::of('203.0.113.1'), PlayerId::random(), RecordedDeal::record(), Seat::First, Lang::Fr, 'Qu’ai-je mal joué ?');

        self::assertSame([AnswerSource::Summary, 1], [$answered->answer->source, $answered->left]);
        self::assertSame(['pace', 'take', 'day'], $this->allowance->asked);
        self::assertSame([], $this->memory->threads ?? null);
    }

    public function testADealTheEngineRefusesIsNotCountedAgainstTheDealOrTheDay(): void
    {
        $record = RecordedDeal::record()->toArray();
        $tampered = DealRecord::fromArray(['tricks' => array_reverse($record['tricks'])] + $record);
        $transport = RecordedDeal::transport();
        $transport->record('/analysis/summary', ['record' => $tampered->toArray(), 'seat' => 0], 422, ['error' => ['code' => 'refused', 'message' => 'no', 'at' => ['trick' => 1]]]);
        $model = new ScriptedCoachModel([]);

        try {
            $this->desk($model, $transport)(NetworkAddress::of('203.0.113.1'), PlayerId::random(), $tampered, Seat::First, Lang::Fr, 'Qu’ai-je mal joué ?');
            self::fail('The engine refuses this deal.');
        } catch (EngineRefused) {
            self::assertSame(['pace'], $this->allowance->asked);
            self::assertSame([], $model->calls);
        }
    }

    public function testAQuestionThatIsNotOneAndADealThatIsSpentAskNothingOfTheModel(): void
    {
        $model = new ScriptedCoachModel([]);
        try {
            $this->desk($model)(NetworkAddress::of('203.0.113.1'), PlayerId::random(), RecordedDeal::record(), Seat::First, Lang::Fr, '   ');
            self::fail('An empty question is refused.');
        } catch (\InvalidArgumentException) {
            self::assertSame([], $this->allowance->asked);
        }

        $this->expectException(DealSpent::class);
        try {
            $this->desk($model, left: 0)(NetworkAddress::of('203.0.113.1'), PlayerId::random(), RecordedDeal::record(), Seat::First, Lang::Fr, 'Qu’ai-je mal joué ?');
        } finally {
            self::assertSame([], $model->calls);
            // asking again about a spent deal takes nothing from the day of everybody else
            self::assertSame(['pace'], $this->allowance->asked);
        }
    }

    public function testAQuestionOnADayThatIsSpentIsNotCountedAgainstTheDeal(): void
    {
        $model = new ScriptedCoachModel([]);
        $desk = $this->desk($model);
        $this->allowance->resting = true;

        try {
            $desk(NetworkAddress::of('203.0.113.1'), PlayerId::random(), RecordedDeal::record(), Seat::First, Lang::Fr, 'Qu’ai-je mal joué ?');
            self::fail('The coach rests for the day.');
        } catch (DaySpent) {
            self::assertSame(['pace', 'take', 'giveBack'], $this->allowance->asked);
            self::assertSame([], $model->calls);
        }
    }

    private function desk(ScriptedCoachModel $model, ?RecordedBridgeTransport $transport = null, int $left = 2): AnswerVisitor
    {
        $transport ??= RecordedDeal::transport();
        $this->allowance = new CountingAllowance($left);
        $this->memory ??= new ListedMemory();

        return new AnswerVisitor(new AskCoach($model, RecordedDeal::summarise($transport), RecordedDeal::rankSwings($transport)), RecordedDeal::summarise($transport), $this->allowance, $this->memory);
    }
}
