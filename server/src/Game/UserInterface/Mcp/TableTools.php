<?php

declare(strict_types=1);

namespace Kabbout\Game\UserInterface\Mcp;

use Kabbout\Game\Application\Table\ActAtTable;
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
use Kabbout\Identity\Domain\Player;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * The table, as an MCP client plays at it. Each tool maps its arguments to one capability of
 * Game\Application\Table and the answer back; no rule of the game and no limit is here.
 *
 * Every answer about a game is the asker's seat's view of it: its own hand and what is public.
 */
final readonly class TableTools
{
    private const string VIEW = ' The answer holds "events" (what the table heard since your action: the robots\' bids and cards, tricks won, the result of the deal), "view" (your hand, the bids, the contract, the trick on the table, the tricks played, the scores, "phase" and whose "turn" it is) and "legal" (the bids or cards you may play now; "next": true when the deal is over).';

    public function __construct(
        private Asker $asker,
        private OpenTable $open,
        private SeeTable $see,
        private ActAtTable $act,
        private ReadScore $readScore,
    ) {
    }

    /**
     * @param Level       $level how well the three robots play their cards; they all bid alike
     * @param int         $seat  the seat you take
     * @param string|null $seed  leave out for a game nobody can predict. For tests and benches only: the same seed and the same play give the same game.
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'new_game', description: 'Sit down at a new table of belote contrée against three robots. The cards are dealt and the robots play until it is your turn. Returns "table", the name to give to every other tool.'.self::VIEW.' Read the resource kabbout://rules first if you do not know the game.')]
    public function newGame(
        Level $level = Level::Normal,
        #[Schema(minimum: 0, maximum: 3)] int $seat = 0,
        #[Schema(pattern: '^[0-9a-f]{32,64}$')] ?string $seed = null,
    ): array {
        return ($this->asker)(fn (Player $player, NetworkAddress $from): array => ($this->open)($player, $from, $level, Seat::from($seat), null === $seed ? null : Seed::fromHex($seed))->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'my_view', description: 'What your seat sees now: your hand, the auction so far, the contract, the trick on the table, the tricks played, the scores, the phase (bidding, playing, roundEnd, gameEnd) and whose turn it is. You never see another hand. Changes nothing.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function myView(string $table): array
    {
        return ($this->asker)(fn (Player $player, NetworkAddress $from): array => ($this->see)($player, $from, $table)->view->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'legal_moves', description: 'Exactly what you may do now: "bids" during the auction (each as type, and value and suit for a bid), "cards" during the play, "next": true once the deal is over. All empty when it is not your turn. Which card is legal depends on the suit led, on your hand, on the trumps already in the trick and on who holds it: take it from here rather than work it out. Changes nothing.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function legalMoves(string $table): array
    {
        return ($this->asker)(fn (Player $player, NetworkAddress $from): array => ($this->see)($player, $from, $table)->legal->toArray());
    }

    /**
     * @param string    $table the table, as new_game named it
     * @param BidType   $type  pass; bid (a value and a trump suit); contre (double the other team's bid); surcontre (redouble, after your team was contred)
     * @param int|null  $value for type "bid": 80 to 160 by tens, or 250 for the capot (all eight tricks)
     * @param Suit|null $suit  for type "bid": the trump suit, S H D or C
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'bid', description: 'Speak in the auction at your turn, then let the robots speak (and play, if the auction ends) until it is your turn again. A bid that is not allowed changes nothing and is answered with the bids that are.'.self::VIEW)]
    public function bid(string $table, BidType $type, ?int $value = null, ?Suit $suit = null): array
    {
        return ($this->asker)(fn (Player $player, NetworkAddress $from): array => ($this->act)($player, $from, $table, Action::bid(Bid::of($type, $value, $suit)))->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     * @param string $card  rank then suit, for example JH: ranks 7 8 9 T J Q K A, suits S H D C
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'play_card', description: 'Play one card of your hand at your turn, then let the robots play until it is your turn again or the deal is over. A card that is not allowed changes nothing and is answered with the cards that are.'.self::VIEW)]
    public function playCard(string $table, #[Schema(pattern: '^[789TJQKA][SHDC]$')] string $card): array
    {
        return ($this->asker)(fn (Player $player, NetworkAddress $from): array => ($this->act)($player, $from, $table, Action::play(Card::fromCode($card)))->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'next_deal', description: 'Once a deal is over (phase roundEnd): deal the next one, or end the game if a team has reached the target. The robots then play until it is your turn. The deal just played can still be reviewed until the next one is over.'.self::VIEW)]
    public function nextDeal(string $table): array
    {
        return ($this->asker)(fn (Player $player, NetworkAddress $from): array => ($this->act)($player, $from, $table, Action::next())->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'score', description: 'The count at your table: "scores" as [team 0, team 1] and "yourTeam"; the contract; once a deal is over its "result" (made or not, the points of each side, the score sheet, what each team gained); the deals before it; "winner" once a team has reached "target". Changes nothing.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function score(string $table): array
    {
        return ($this->asker)(fn (Player $player, NetworkAddress $from): array => ($this->readScore)($player, $from, $table)->toArray());
    }
}
