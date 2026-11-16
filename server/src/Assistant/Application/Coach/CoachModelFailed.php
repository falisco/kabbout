<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

/** The model gave no answer. The player still gets one: the plain summary, built without it. */
final class CoachModelFailed extends \RuntimeException
{
}
