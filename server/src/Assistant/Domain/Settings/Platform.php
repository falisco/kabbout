<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain\Settings;

/** Where the models are asked. One platform today; another one is one case here, its bridge package and its lines of configuration. */
enum Platform: string
{
    case Anthropic = 'anthropic';
}
