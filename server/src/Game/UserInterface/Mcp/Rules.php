<?php

declare(strict_types=1);

namespace Kabbout\Game\UserInterface\Mcp;

use Mcp\Capability\Attribute\McpResource;

/**
 * Belote contrée in a few lines, for a client that has never heard of it. It is what the tools assume
 * their reader knows, said once: the server's instructions (config/packages/mcp.yaml) are this text too.
 *
 * The rules themselves are the engine's, and nothing here decides anything: this is a reader's summary
 * of engine/src/rules.ts, game.ts and scoring.ts, and of the README's "Rules". Where the count has
 * cases (a contre, a capot, a failed contract) it sends the reader to the result the engine gives.
 */
final readonly class Rules
{
    public const string TEXT = <<<'TXT'
        Kabbout is belote contrée (French rules, FFB 2016). You sit at one seat of a table; robots play the three others.
        Seats are 0 to 3 and act in that order, round and round. Seats 0 and 2 are team 0, seats 1 and 3 team 1: your partner is (your seat + 2) mod 4.
        A card is written rank then suit. Ranks: 7 8 9 T J Q K A (T is the ten). Suits: S spades, H hearts, D diamonds, C clubs. JH is the jack of hearts.
        A deal: 32 cards, 8 each; the auction, then 8 tricks. The seat after the dealer speaks first and leads the first trick.
        Auction: at your turn, pass, or bid a value with a trump suit: 80 to 160 by tens, or 250 (the capot: your team takes all eight tricks). A bid must be higher than the best so far. The last bid is the contract; its bidder's team are the takers.
        Contre: at your turn, against the other team's best bid. It doubles the stakes and closes the bidding; the takers still answer in turn, pass or surcontre (four times the stakes, and the auction ends at once).
        Three passes after a bid end the auction. Four passes with no bid: the next dealer deals again.
        Trumps, strongest first: J 9 A T K Q 8 7, worth 20 14 11 10 4 3 0 0. Other suits, strongest first: A T K Q J 9 8 7, worth 11 10 4 3 2 0 0 0.
        A trick goes to the highest trump in it, or with no trump to the highest card of the suit led; its winner leads the next. The last trick is worth 10 more: 162 points in a deal.
        Which card you may play: follow the suit led if you can; when trumps are led, with a trump higher than those in the trick if you have one. With none of the suit led: anything if your partner holds the trick; otherwise a trump higher than those in the trick if you have one, and anything if you have none.
        Do not work this out yourself: legal_moves, and every answer of bid and play_card, lists exactly what you may do.
        Belote: the king and queen of trumps in one hand are worth 20 points once both are played. It is announced for you.
        The contract is made when the takers' points (tricks, last trick, belote) reach the value bid; a capot, only by taking all eight tricks. Made: each team scores its points and the takers add the value bid. Failed: the defence scores 160 plus the value bid, the takers nothing. After a contre only one side scores. "result" in the answers is the exact count; scores are rounded to tens.
        The game is to 1000 points ("target"). Call next_deal after each deal, until "winner" is set.
        Once a deal is over you can review your own play: deal_summary, trick, expert_choice, what_if, expert_bids, biggest_swings. In their answers "the player" is you.
        TXT;

    #[McpResource(uri: 'kabbout://rules', name: 'rules', title: 'Belote contrée in a few lines', description: 'Card notation, seats and teams, the auction, the order and worth of the cards, which card may be played, how a deal is scored.', mimeType: 'text/plain')]
    public function rules(): string
    {
        return self::TEXT;
    }
}
