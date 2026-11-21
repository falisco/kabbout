<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Ai;

use Kabbout\Game\Domain\ConventionCard;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * The answer asked of the model that reads a player's conventions: the Symfony AI bundle turns this
 * class into the JSON schema the platform must answer in (structured output), and the answer back into
 * this class. Every value a setting may take is listed (`enum`), taken from Game\Domain\ConventionCard:
 * the Anthropic bridge sends a list as it is, where it would turn a range into a sentence. It is still
 * the model's word, and the same ConventionCard checks it.
 */
final readonly class CardDraft
{
    /**
     * @param list<string> $unplaced
     */
    public function __construct(
        #[Schema(description: 'false when the sentence says nothing about how the two partners bid; true otherwise')]
        public bool $understood,
        #[Schema(description: 'What the best suit of a hand must be worth for it to open the auction. Classic: '.ConventionCard::READY['classic']['open'].'.', enum: ConventionCard::VALUES['open'])]
        public int $open,
        #[Schema(description: 'What the partner adds to the bid for holding the jack of the trump suit named. Classic: '.ConventionCard::READY['classic']['jack'].'.', enum: ConventionCard::VALUES['jack'])]
        public int $jack,
        #[Schema(description: 'What the partner adds for the nine of trumps. Classic: '.ConventionCard::READY['classic']['nine'].'.', enum: ConventionCard::VALUES['nine'])]
        public int $nine,
        #[Schema(description: 'What the partner adds for holding three trumps or more. Classic: '.ConventionCard::READY['classic']['threeTrumps'].'.', enum: ConventionCard::VALUES['threeTrumps'])]
        public int $threeTrumps,
        #[Schema(description: 'What the partner adds for the belote, king and queen of trumps. Classic: '.ConventionCard::READY['classic']['belote'].'.', enum: ConventionCard::VALUES['belote'])]
        public int $belote,
        #[Schema(description: 'What the partner adds for each ace outside the trump suit. Classic: '.ConventionCard::READY['classic']['ace'].'.', enum: ConventionCard::VALUES['ace'])]
        public int $ace,
        #[Schema(description: 'When the partner shows its outside aces: atOnce, with its first raise (classic); whenOverbid, kept back and shown only if the opponents bid over the pair (a second round).', enum: ConventionCard::VALUES['aces'])]
        public string $aces,
        #[Schema(description: 'The pieces of the sentence that speak of how to bid or play and that no setting above can hold, each copied word for word from the sentence. Empty when everything was placed.')]
        public array $unplaced,
    ) {
    }
}
