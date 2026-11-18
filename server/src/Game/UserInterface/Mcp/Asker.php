<?php

declare(strict_types=1);

namespace Kabbout\Game\UserInterface\Mcp;

use Kabbout\Game\Application\Table\TableRefusal;
use Kabbout\Game\Application\Table\TablesUnavailable;
use Kabbout\Game\Domain\Exception\EngineRefused;
use Kabbout\Game\Domain\Exception\EngineUnavailable;
use Kabbout\Identity\Application\CurrentPlayer;
use Kabbout\Identity\Application\EnteredTooOften;
use Kabbout\Identity\Application\NoNameFree;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\Player;
use Kabbout\Identity\Domain\PlayersUnavailable;
use Mcp\Exception\ToolCallException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * What every MCP tool does around its capability: say who is asking and from where, and turn what the
 * capability refuses into an answer the client's model can read and act on. A tool error (isError) is
 * read by the model; any other exception would reach it as "internal error" and teach it nothing.
 */
final readonly class Asker
{
    /** @param CurrentPlayer $current over HTTP whoever Security recognised, over stdio the process's own guest (config/services.yaml) */
    public function __construct(private CurrentPlayer $current, private RequestStack $requests)
    {
    }

    /**
     * @param \Closure(Player, NetworkAddress): array<string, mixed> $ask
     *
     * @return array<string, mixed>
     *
     * @throws ToolCallException
     */
    public function __invoke(\Closure $ask): array
    {
        try {
            $player = $this->current->player() ?? throw new ToolCallException('Nobody is asking: this server does not know who you are.');

            return $ask($player, NetworkAddress::of($this->requests->getCurrentRequest()?->getClientIp()));
        } catch (TableRefusal $refusal) {
            throw new ToolCallException($refusal->getMessage(), previous: $refusal);
        } catch (EngineRefused $refused) {
            throw new ToolCallException('Refused: '.$refused->getMessage(), previous: $refused);
        } catch (\InvalidArgumentException $invalid) {
            // a value of the domain that refused to be built: its message says what one is
            throw new ToolCallException($invalid->getMessage(), previous: $invalid);
        } catch (EnteredTooOften|NoNameFree $full) {
            throw new ToolCallException('Too many new players right now. Try again in a minute.', previous: $full);
        } catch (EngineUnavailable|TablesUnavailable|PlayersUnavailable $down) {
            throw new ToolCallException('The game cannot be reached right now. Nothing has changed; try again shortly.', previous: $down);
        }
    }
}
