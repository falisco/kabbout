<?php

declare(strict_types=1);

namespace Kabbout\Game\UserInterface\Mcp;

use Kabbout\Game\Application\Table\ActAtTable;
use Kabbout\Game\Application\Table\ListMoves;
use Kabbout\Game\Application\Table\OpenTable;
use Kabbout\Game\Application\Table\ReadScore;
use Kabbout\Game\Application\Table\SeeTable;
use Kabbout\Game\Domain\Action;
use Kabbout\Game\Domain\Bid;
use Kabbout\Game\Domain\BidType;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\Level;
use Kabbout\Game\Domain\Seat;
use Kabbout\Game\Domain\Seed;
use Kabbout\Game\Domain\Suit;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayerId;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;

/**
 * The table, as an MCP client plays at it. Each tool maps its arguments to one capability of
 * Game\Application\Table and the answer back, and does nothing else: no rule of the game, no limit, no
 * choice (tests/Architecture/DoorsTest.php reads this file and fails on an `if`). What an argument may
 * be is said by the domain's own types and formats.
 *
 * Every answer about a game is the asker's seat's view of it: its own hand and what is public.
 */
final readonly class TableTools
{
    private const string TURN = ' The answer holds "table"; "events", what the table heard since your action (the robots\' bids and cards, tricks won, the result of the deal); "view", what your seat sees (see my_view); and "legal", what you may do now (see legal_moves).';

    public function __construct(
        private Door $door,
        private OpenTable $open,
        private SeeTable $see,
        private ListMoves $listMoves,
        private ActAtTable $act,
        private ReadScore $readScore,
    ) {
    }

    /**
     * @param Level       $level how well the three robots play their cards; they all bid alike
     * @param Seat        $seat  the seat you take, 0 to 3
     * @param string|null $seed  leave out for a game nobody can predict. For tests and benches only: the same seed and the same play give the same first deal.
     */
    #[McpTool(name: 'new_game', description: 'Sit down at a new table of belote contrée against three robots. The cards are dealt and the robots play until it is your turn. "table" in the answer is the name to give to every other tool.'.self::TURN.' Read the resource kabbout://rules first if you do not know the game.', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false))]
    public function newGame(Level $level = Level::Normal, Seat $seat = Seat::First, #[Schema(pattern: Seed::FORMAT)] ?string $seed = null): CallToolResult
    {
        return ($this->door)(fn (PlayerId $player, NetworkAddress $from): array => ($this->open)($player, $from, $level, $seat, Seed::fromHexOrNone($seed))->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     */
    #[McpTool(name: 'my_view', description: 'What your seat sees now. "seat" and "hand" are yours, and you never see another hand ("handSizes" says how many cards each seat holds). "phase" is bidding, playing, roundEnd or gameEnd, and "turn" the seat to act. The auction: "bids" so far, "best" the highest, "coinche" 0, 1 after a contre, 2 after a surcontre. The play: "contract" (seat, value, suit, mult), "trick" on the table, "tricks" played, "belote" once announced. The count: "scores" as [team 0, team 1], "target", and once the deal is over its "result". Changes nothing.', annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function myView(string $table): CallToolResult
    {
        return ($this->door)(fn (PlayerId $player, NetworkAddress $from): array => ($this->see)($player, $from, $table)->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     */
    #[McpTool(name: 'legal_moves', description: 'Exactly what you may do now: "bids" during the auction (each as type, and value and suit for a bid), "cards" during the play, "next": true once the deal is over. All empty when it is not your turn. Which card is legal depends on the suit led, on your hand, on the trumps already in the trick and on who holds it: take it from here rather than work it out. Changes nothing.', annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function legalMoves(string $table): CallToolResult
    {
        return ($this->door)(fn (PlayerId $player, NetworkAddress $from): array => ($this->listMoves)($player, $from, $table)->toArray());
    }

    /**
     * @param string    $table the table, as new_game named it
     * @param BidType   $type  pass; bid (a value and a trump suit); contre (double the other team's bid); surcontre (redouble, after your team was contred)
     * @param int|null  $value for type "bid": the points your team undertakes to make, as legal_moves lists them
     * @param Suit|null $suit  for type "bid": the trump suit, S H D or C
     */
    #[McpTool(name: 'bid', description: 'Speak in the auction at your turn, then let the robots speak (and play, if the auction ends) until it is your turn again. A bid that is not allowed changes nothing and is answered with the bids that are.'.self::TURN, annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false))]
    public function bid(string $table, BidType $type, ?int $value = null, ?Suit $suit = null): CallToolResult
    {
        return ($this->door)(fn (PlayerId $player, NetworkAddress $from): array => ($this->act)($player, $from, $table, Action::bid(Bid::of($type, $value, $suit)))->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     * @param string $card  rank then suit, for example JH: ranks 7 8 9 T J Q K A, suits S H D C
     */
    #[McpTool(name: 'play_card', description: 'Play one card of your hand at your turn, then let the robots play until it is your turn again or the deal is over. A card that is not allowed changes nothing and is answered with the cards that are.'.self::TURN, annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false))]
    public function playCard(string $table, #[Schema(pattern: Card::FORMAT)] string $card): CallToolResult
    {
        return ($this->door)(fn (PlayerId $player, NetworkAddress $from): array => ($this->act)($player, $from, $table, Action::play(Card::fromCode($card)))->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     */
    #[McpTool(name: 'next_deal', description: 'Once a deal is over (phase roundEnd): deal the next one, or end the game if a team has won. The robots then play until it is your turn. The deal just played can still be reviewed until the next one is over.'.self::TURN, annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false))]
    public function nextDeal(string $table): CallToolResult
    {
        return ($this->door)(fn (PlayerId $player, NetworkAddress $from): array => ($this->act)($player, $from, $table, Action::next())->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     */
    #[McpTool(name: 'score', description: 'The count at your table: "scores" as [team 0, team 1] and "team", yours; the contract; once a deal is over its "result" (made or not, the points of each side, the score sheet, what each team gained); the deals before it; "winner" once a team has won. Changes nothing.', annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function score(string $table): CallToolResult
    {
        return ($this->door)(fn (PlayerId $player, NetworkAddress $from): array => ($this->readScore)($player, $from, $table)->toArray());
    }
}
