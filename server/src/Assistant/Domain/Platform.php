<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain;

/** Where the models are asked. One platform today; another one is one case here and its bridge package. */
enum Platform: string
{
    case HuggingFace = 'huggingface';
}
