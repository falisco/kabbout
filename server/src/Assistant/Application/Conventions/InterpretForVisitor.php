<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Conventions;

use Kabbout\Assistant\Application\Allowance\AskedTooOften;
use Kabbout\Assistant\Application\Allowance\DaySpent;
use Kabbout\Assistant\Application\Allowance\ModelAllowance;
use Kabbout\Game\Domain\Lang;
use Kabbout\Identity\Domain\NetworkAddress;

/**
 * A visitor's sentence, read for them. Each one is a call to a model that is paid for: it is counted
 * as any call to the model is, against the visitor's pace and against the day all visitors share. A
 * sentence that is none costs nothing. Every door that lets a visitor in goes through here.
 */
final readonly class InterpretForVisitor
{
    public function __construct(private InterpretConventions $interpret, private ModelAllowance $allowance)
    {
    }

    /**
     * @throws InvalidSentence        a sentence that is empty or too long
     * @throws AskedTooOften
     * @throws DaySpent
     * @throws NotUnderstood
     * @throws ConventionsModelFailed
     */
    public function __invoke(NetworkAddress $from, string $sentence, Lang $lang): Interpreted
    {
        $sentence = InterpretConventions::sentence($sentence);
        $this->allowance->pace($from);
        $this->allowance->day();

        return ($this->interpret)($sentence, $lang);
    }
}
