<?php

declare(strict_types=1);

namespace Kabbout\Identity\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;

/**
 * A guest's secret out of `Authorization: Bearer <secret>`. Symfony's own extractor reads the scheme
 * in that one spelling; the scheme of this header is case-insensitive (RFC 9110), and a client that
 * writes `bearer` is not wrong.
 */
final readonly class BearerSecret implements AccessTokenExtractorInterface
{
    public function extractAccessToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');

        return \is_string($header) && 1 === preg_match('/^Bearer +(\S+)$/Di', $header, $found) ? $found[1] : null;
    }
}
