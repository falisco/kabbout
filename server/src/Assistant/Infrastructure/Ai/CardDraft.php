<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Ai;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * The answer asked of the model that reads a player's conventions: the Symfony AI bundle turns this
 * class into the JSON schema the platform must answer in (structured output), and the answer back into
 * this class. Every value a setting may take is listed (`enum`): a platform enforces a list, where it
 * would only be told of a range. It is still the model's word, checked by Game\Domain\ConventionCard.
 */
final readonly class CardDraft
{
    /**
     * @param list<string> $unplaced
     */
    public function __construct(
        #[Schema(description: 'false when the sentence says nothing about how the two partners bid; true otherwise')]
        public bool $understood,
        #[Schema(description: 'What the best suit of a hand must be worth for it to open the auction. Classic: 70.', enum: [70, 80, 90])]
        public int $open,
        #[Schema(description: 'What the partner adds to the bid for holding the jack of the trump suit named. Classic: 20.', enum: [0, 10, 20, 30])]
        public int $jack,
        #[Schema(description: 'What the partner adds for the nine of trumps. Classic: 10.', enum: [0, 10, 20])]
        public int $nine,
        #[Schema(description: 'What the partner adds for holding three trumps or more. Classic: 10.', enum: [0, 10, 20])]
        public int $threeTrumps,
        #[Schema(description: 'What the partner adds for the belote, king and queen of trumps. Classic: 20.', enum: [0, 10, 20, 30])]
        public int $belote,
        #[Schema(description: 'What the partner adds for each ace outside the trump suit. Classic: 10.', enum: [0, 10, 20])]
        public int $ace,
        #[Schema(description: 'When the partner shows its outside aces: atOnce, with its first raise (classic); whenOverbid, kept back and shown only if the opponents bid over the pair (a second round).', enum: ['atOnce', 'whenOverbid'])]
        public string $aces,
        #[Schema(description: 'The pieces of the sentence that speak of how to bid or play and that no setting above can hold, each copied word for word from the sentence. Empty when everything was placed.')]
        public array $unplaced,
    ) {
    }
}
