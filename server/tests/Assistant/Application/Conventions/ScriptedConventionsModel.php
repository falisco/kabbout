<?php

declare(strict_types=1);

namespace Kabbout\Tests\Assistant\Application\Conventions;

use Kabbout\Assistant\Application\Conventions\ConventionsModel;
use Kabbout\Assistant\Application\Conventions\ConventionsModelFailed;
use Kabbout\Assistant\Application\Conventions\Reading;
use Kabbout\Game\Domain\Lang;

/** A model for tests: it gives the readings it was scripted to give, in order, and writes down what it was asked. */
final class ScriptedConventionsModel implements ConventionsModel
{
    /** @var list<array{string, Lang}> */
    public array $asked = [];

    /** @param list<Reading> $readings when none is left, the model fails */
    public function __construct(private array $readings)
    {
    }

    public function read(string $sentence, Lang $lang): Reading
    {
        $this->asked[] = [$sentence, $lang];

        return array_shift($this->readings) ?? throw new ConventionsModelFailed('The model gave no reading.');
    }
}
