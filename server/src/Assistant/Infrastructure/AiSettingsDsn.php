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
 * A key that holds "@", "/" or "%" is written percent-encoded, as in any address.
 * An error never repeats any part of the value: a part put in the wrong place may be the key.
 */
final class AiSettingsDsn
{
    public const string FORM = '<platform>://<key>@<provider>/<model>';
    private const string ANY_PROVIDER = 'auto';
    private const string PATTERN = '#^(?<platform>[a-z][a-z0-9+.-]*)://(?<key>[^@/\s]+)@(?<provider>[a-z0-9][a-z0-9-]*)/(?<model>[\w.:/-]+)$#i';

    /** @throws InvalidAiSettings */
    public static function parse(#[\SensitiveParameter] string $dsn): ?AiSettings
    {
        $dsn = trim($dsn);
        if ('' === $dsn) {
            return null;
        }
        if (1 !== preg_match(self::PATTERN, $dsn, $parts)) {
            throw new InvalidAiSettings(\sprintf('The AI settings are not written as %s.', self::FORM));
        }
        $platform = Platform::tryFrom(strtolower($parts['platform']))
            ?? throw new InvalidAiSettings(\sprintf('The AI platform is not known. Known: %s.', implode(', ', array_column(Platform::cases(), 'value'))));
        $key = rawurldecode($parts['key']);
        if (1 === preg_match('/[\s\x00-\x1F\x7F]/', $key)) {
            throw new InvalidAiSettings('The key of the AI settings holds a space or a control character.');
        }

        return new AiSettings($platform, $key, $parts['model'], self::ANY_PROVIDER === strtolower($parts['provider']) ? null : $parts['provider']);
    }
}
