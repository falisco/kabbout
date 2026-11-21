<?php

declare(strict_types=1);

namespace Kabbout\Game\UserInterface\Mcp;

use Kabbout\Game\Application\AskExpertChoice;
use Kabbout\Game\Application\RankSwings;
use Kabbout\Game\Application\ReviewBids;
use Kabbout\Game\Application\ReviewTrick;
use Kabbout\Game\Application\SummariseDeal;
use Kabbout\Game\Application\Table\FinishedDeal;
use Kabbout\Game\Application\Table\PlayedDeal;
use Kabbout\Game\Application\TryWhatIf;
use Kabbout\Game\Domain\Analysis\TrickNumber;
use Kabbout\Game\Domain\Card;
use Kabbout\Game\Domain\Lang;
use Kabbout\Identity\Domain\NetworkAddress;
use Kabbout\Identity\Domain\PlayerId;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;

/**
 * The review of a deal, as an MCP client asks for it: the analyses the coach's own tools read, over
 * the same capabilities. The deal is always the last one played to its end at the asker's table, as
 * the server holds it: a client sends the name of its table and never a deal. Before the eight tricks
 * are played there is nothing to review, so no hidden card can be learnt here.
 */
final readonly class ReviewTools
{
    private const string WHICH = ' About the last deal played to its end at your table; "the player" is you. Changes nothing.';

    public function __construct(
        private Door $door,
        private FinishedDeal $finished,
        private SummariseDeal $summarise,
        private ReviewTrick $reviewTrick,
        private AskExpertChoice $askExpertChoice,
        private TryWhatIf $tryWhatIf,
        private RankSwings $rankSwings,
        private ReviewBids $reviewBids,
    ) {
    }

    /**
     * @param string $table the table, as new_game named it
     */
    #[McpTool(name: 'deal_summary', description: SummariseDeal::ABOUT.self::WHICH, annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function dealSummary(string $table): CallToolResult
    {
        return $this->review($table, fn (PlayedDeal $deal): array => ($this->summarise)($deal->record, $deal->seat)->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     * @param int    $n     the trick, from 1 to 8
     */
    #[McpTool(name: 'trick', description: ReviewTrick::ABOUT.self::WHICH, annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function trick(string $table, #[Schema(minimum: TrickNumber::FIRST, maximum: TrickNumber::LAST)] int $n): CallToolResult
    {
        return $this->review($table, fn (PlayedDeal $deal): array => ($this->reviewTrick)($deal->record, $deal->seat, TrickNumber::of($n))->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     * @param int    $n     the trick, from 1 to 8
     */
    #[McpTool(name: 'expert_choice', description: AskExpertChoice::ABOUT.self::WHICH, annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function expertChoice(string $table, #[Schema(minimum: TrickNumber::FIRST, maximum: TrickNumber::LAST)] int $n): CallToolResult
    {
        return $this->review($table, fn (PlayedDeal $deal): array => ($this->askExpertChoice)($deal->record, $deal->seat, TrickNumber::of($n))->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     * @param int    $n     the trick, from 1 to 8
     * @param string $card  the card to play instead, rank then suit, for example QH
     */
    #[McpTool(name: 'what_if', description: TryWhatIf::ABOUT.self::WHICH, annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function whatIf(string $table, #[Schema(minimum: TrickNumber::FIRST, maximum: TrickNumber::LAST)] int $n, #[Schema(pattern: Card::FORMAT)] string $card): CallToolResult
    {
        return $this->review($table, fn (PlayedDeal $deal): array => ($this->tryWhatIf)($deal->record, $deal->seat, TrickNumber::of($n), Card::fromCode($card))->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     */
    #[McpTool(name: 'expert_bids', description: ReviewBids::ABOUT.self::WHICH, annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function expertBids(string $table): CallToolResult
    {
        return $this->review($table, fn (PlayedDeal $deal): array => ($this->reviewBids)($deal->record, $deal->seat, Lang::En)->toArray());
    }

    /**
     * @param string $table the table, as new_game named it
     */
    #[McpTool(name: 'biggest_swings', description: RankSwings::ABOUT.self::WHICH, annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function biggestSwings(string $table): CallToolResult
    {
        return $this->review($table, fn (PlayedDeal $deal): array => ($this->rankSwings)($deal->record, $deal->seat)->toArray());
    }

    /** @param \Closure(PlayedDeal): array<string, mixed> $look */
    private function review(string $table, \Closure $look): CallToolResult
    {
        return ($this->door)(fn (PlayerId $player, NetworkAddress $from): array => $look(($this->finished)($player, $from, $table)));
    }
}
