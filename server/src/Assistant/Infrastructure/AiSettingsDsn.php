<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure;

use Kabbout\Assistant\Domain\AiSettings;
use Kabbout\Assistant\Domain\InvalidAiSettings;
use Kabbout\Assistant\Domain\Platform;

/**
 * The one variable that switches the AI on, written as an address:
 *
 *     <platform>://<key>@<provider>/<model>      huggingface://hf_xxx@auto/openai/gpt-oss-120b
 *
 * The provider "auto" leaves the choice to the platform. Nothing written means the AI is off.
 * An error never repeats the value: it holds the key.
 */
final class AiSettingsDsn
{
    public const string FORM = '<platform>://<key>@<provider>/<model>';
    private const string ANY_PROVIDER = 'auto';

    /** @throws InvalidAiSettings */
    public static function parse(#[\SensitiveParameter] string $dsn): ?AiSettings
    {
        $dsn = trim($dsn);
        if ('' === $dsn) {
            return null;
        }
        if (1 !== preg_match('#^(?<platform>[a-z][a-z0-9+.-]*)://(?<key>[^@/\s]+)@(?<provider>[^/\s]+)/(?<model>\S+)$#i', $dsn, $parts)) {
            throw new InvalidAiSettings(\sprintf('The AI settings are not written as %s.', self::FORM));
        }
        $platform = Platform::tryFrom(strtolower($parts['platform']))
            ?? throw new InvalidAiSettings(\sprintf('The AI platform "%s" is not known. Known: %s.', $parts['platform'], implode(', ', array_column(Platform::cases(), 'value'))));

        return new AiSettings($platform, rawurldecode($parts['key']), $parts['model'], self::ANY_PROVIDER === $parts['provider'] ? null : $parts['provider']);
    }
}
