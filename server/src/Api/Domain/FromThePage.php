<?php

declare(strict_types=1);

namespace Kabbout\Api\Domain;

/**
 * What a request that changes something must show: that the game's own page made it, and not a page of
 * another site in the visitor's browser. Symfony's same-origin check answers (framework.csrf_protection):
 * a browser says where a request comes from, in Sec-Fetch-Site or else in Origin or Referer, and the
 * request must carry the header below, which a page of another site cannot add. A client that is no
 * browser sends the same two things: the header, and an Origin that names the server.
 *
 * This is the one home of the two words; every door that takes a POST names them in its attribute.
 */
final readonly class FromThePage
{
    /** the name the check is configured under (framework.csrf_protection.stateless_token_ids) */
    public const string CHECK = 'api';

    /** the header to send, and the value to give it: Symfony's own name for it */
    public const string HEADER = 'csrf-token';
}
