<?php

declare(strict_types=1);

namespace Kabbout\Tests\Support;

use Kabbout\Game\Infrastructure\Bridge\Mapping\Json;
use Kabbout\Game\Infrastructure\Bridge\Transport\BridgeResponse;
use Kabbout\Game\Infrastructure\Bridge\Transport\BridgeTransport;

/**
 * A bridge for unit tests, with none running: it only knows the answers it was given, and gives each
 * back to the question it was recorded for. The calls of the contract (bridge/contract/cases.json)
 * were recorded from the real bridge. It is a recording, not a game: a question it was never given is
 * a mistake in the test and fails loudly. A test that needs a game played out asks the real bridge.
 */
final class RecordedBridgeTransport implements BridgeTransport
{
    /** @var array<string, BridgeResponse> */
    private array $answers = [];

    /** The transport that knows every call of a contract file. */
    public static function fromContractFile(string $file): self
    {
        $transport = new self();
        $content = file_get_contents($file);
        if (false === $content) {
            throw new \InvalidArgumentException(\sprintf('No contract file at %s.', $file));
        }
        foreach (Json::objects(json_decode($content, true, flags: \JSON_THROW_ON_ERROR), 'calls') as $case) {
            $path = \is_string($case['path'] ?? null) ? $case['path'] : throw new \InvalidArgumentException('A recorded call has no path.');
            $transport->record($path, Json::object($case['request'] ?? null, 'request'), Json::int($case['status'] ?? null, 'status'), $case['response'] ?? null);
        }

        return $transport;
    }

    /**
     * Teaches the transport one answer.
     *
     * @param array<string, mixed> $request
     */
    public function record(string $path, array $request, int $status, mixed $response): void
    {
        $this->answers[self::key($path, $request)] = new BridgeResponse($status, $response);
    }

    public function post(string $path, array $body): BridgeResponse
    {
        return $this->answers[self::key($path, $body)] ?? throw new \LogicException(\sprintf('The recorded bridge was never given an answer to this call of %s.', $path));
    }

    /**
     * A question, written the same way whatever the order of its fields.
     *
     * @param array<string, mixed> $request
     */
    private static function key(string $path, array $request): string
    {
        return $path."\n".json_encode(Canonical::sorted($request), \JSON_THROW_ON_ERROR);
    }
}
