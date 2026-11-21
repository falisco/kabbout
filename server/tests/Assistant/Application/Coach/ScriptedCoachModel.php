<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Application\Coach;

use Kabbout\Assistant\Application\Coach\CoachBrief;
use Kabbout\Assistant\Application\Coach\CoachModel;
use Kabbout\Assistant\Application\Coach\CoachModelFailed;
use Kabbout\Assistant\Application\Coach\Rejected;
use Kabbout\Assistant\Domain\Grounding\Facts;

/** A model for tests: it says what it was told to say, in order, after its tools returned what they were told to return. */
final class ScriptedCoachModel implements CoachModel
{
    /** @var list<Rejected|null> what each call was given to correct */
    public array $calls = [];
    /** @var list<float|null> until when each call may look at the deal */
    public array $untils = [];
    /** @var list<CoachBrief> what each call was asked */
    public array $briefs = [];

    /**
     * @param list<string|CoachModelFailed> $answers
     * @param list<mixed>                   $toolResults added to the facts of each question, when it is first asked
     */
    public function __construct(private array $answers, private array $toolResults = [])
    {
    }

    public function answer(CoachBrief $brief, Facts $facts, ?Rejected $rejected = null, ?float $until = null): string
    {
        // every question starts with no fact: its tools return what they were told to
        if ($facts->isEmpty()) {
            array_map($facts->add(...), $this->toolResults);
        }
        $this->calls[] = $rejected;
        $this->briefs[] = $brief;
        $this->untils[] = $until;
        $answer = $this->answers[\count($this->calls) - 1] ?? throw new \LogicException('The model was asked more often than scripted.');

        return $answer instanceof CoachModelFailed ? throw $answer : $answer;
    }
}
