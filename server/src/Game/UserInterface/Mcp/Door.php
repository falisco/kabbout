<?php

declare(strict_types=1);

namespace Kabbout\Game\UserInterface\Mcp;

use Kabbout\Game\Application\Table\MoveRefused;
use Kabbout\Game\Application\Table\NoSuchTable;
use Kabbout\Game\Application\Table\TableExpired;
use Kabbout\Game\Application\Table\TableRefusal;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Game\Domain\Exception\InvalidBid;
use Kabbout\Game\Domain\Exception\InvalidConventionCard;
use Kabbout\Game\Domain\Table\TablesUnavailable;
use Kabbout\Identity\Application\CurrentPlayer;
use Kabbout\Identity\Application\EnteredTooOften;
use Kabbout\Identity\Application\NoNameFree;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayerId;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * What every MCP tool has around its capability, said once: who is asking and from where on the way
 * in; on the way out, the answer as a client's model reads it, or a refusal in words it can act on.
 *
 * An answer is sent as data and as one line of JSON. Left to the SDK the text would be the same JSON
 * spread over a line per value, nearly three times the characters, and the text is what most clients
 * hand their model.
 *
 * A refusal is a tool error (isError), which the model is shown. Only what a capability is known to
 * refuse is turned into one: anything else is a mistake of the program, and stays one.
 */
final readonly class Door
{
    /** what the tools are called here: the words a refusal needs to say what to do next */
    private const string OPEN_ONE = ' Open one with new_game.';

    /** @param CurrentPlayer $current over HTTP whoever Security recognised, over stdio the process's own guest (config/services.yaml) */
    public function __construct(private CurrentPlayer $current, private RequestStack $requests)
    {
    }

    /**
     * @param \Closure(PlayerId, NetworkAddress): array<string, mixed> $ask
     *
     * @throws ToolCallException
     */
    public function __invoke(\Closure $ask): CallToolResult
    {
        try {
            $player = $this->current->player() ?? throw new ToolCallException('Nobody is asking: this server does not know who you are.');
            $answer = $ask($player->id, NetworkAddress::of($this->requests->getCurrentRequest()?->getClientIp()));

            return new CallToolResult([new TextContent(self::written($answer))], structuredContent: $answer);
        } catch (MoveRefused $refused) {
            throw new ToolCallException(\sprintf('Refused: %s. Nothing has changed. What you may do now: %s', rtrim($refused->getMessage(), '.'), self::written($refused->legal->toArray())), previous: $refused);
        } catch (NoSuchTable|TableExpired $gone) {
            throw new ToolCallException($gone->getMessage().self::OPEN_ONE, previous: $gone);
        } catch (TableRefusal|InvalidBid|InvalidConventionCard $refusal) {
            throw new ToolCallException($refusal->getMessage(), previous: $refusal);
        } catch (EngineRefused $refused) {
            throw new ToolCallException(\sprintf('Refused: %s.', rtrim($refused->getMessage(), '.')), previous: $refused);
        } catch (EnteredTooOften|NoNameFree $full) {
            throw new ToolCallException('Too many new players right now. Try again in a minute.', previous: $full);
        } catch (EngineUnavailable|TablesUnavailable|PlayersUnavailable $down) {
            throw new ToolCallException('The game cannot be reached right now. Nothing has changed; try again shortly.', previous: $down);
        }
    }

    /** @param array<array-key, mixed> $answer */
    private static function written(array $answer): string
    {
        return json_encode($answer, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}
