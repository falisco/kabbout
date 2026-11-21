<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain\Settings;

enum FeatureState: string
{
    /** not in this version yet: no setting switches it on */
    case Planned = 'planned';
    /** in this version, waiting for the AI to be switched on */
    case Off = 'off';
    case On = 'on';

    public static function of(bool $shipped, bool $aiOn): self
    {
        if (!$shipped) {
            return self::Planned;
        }

        return $aiOn ? self::On : self::Off;
    }
}
