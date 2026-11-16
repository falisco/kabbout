<?php

declare(strict_types=1);

namespace Kabbout\Infrastructure\Engine\Bridge\Transport;

use Kabbout\Domain\Engine\Exception\EngineUnavailable;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** The bridge asked over HTTP, where it runs next to the server. */
final readonly class HttpBridgeTransport implements BridgeTransport
{
    /**
     * @param string $baseUrl where the bridge listens, such as http://bridge:8787
     * @param float  $timeout seconds a call may take; the slowest one, an expert robot's card, takes about a tenth
     */
    public function __construct(
        private HttpClientInterface $client,
        private string $baseUrl,
        private float $timeout = 5.0,
    ) {
    }

    public function post(string $path, array $body): BridgeResponse
    {
        try {
            $response = $this->client->request('POST', rtrim($this->baseUrl, '/').$path, [
                'json' => $body,
                'timeout' => $this->timeout,
                'max_duration' => $this->timeout,
            ]);

            return new BridgeResponse($response->getStatusCode(), $response->toArray(false));
        } catch (ExceptionInterface $e) {
            throw new EngineUnavailable(\sprintf('The bridge could not be asked %s: %s', $path, $e->getMessage()), previous: $e);
        }
    }
}
