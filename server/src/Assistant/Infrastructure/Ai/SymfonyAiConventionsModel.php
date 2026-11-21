<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Ai;

use Kabbout\Assistant\Application\Conventions\ConventionsModel;
use Kabbout\Assistant\Application\Conventions\ConventionsModelFailed;
use Kabbout\Assistant\Application\Conventions\Reading;
use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Game\Domain\Lang;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The model that reads conventions: the agent `conventions` of the Symfony AI bundle
 * (config/packages/ai.yaml), which has no tool, asked for structured output. The `response_format`
 * option names the class the answer must fit (CardDraft); the bundle sends its JSON schema to the
 * platform and gives the answer back as that class.
 *
 * The bundle validates nothing here (symfony/validator is not installed, and beside thinking it would
 * be skipped: symfony/ai#2945), and a platform is told of a schema, not bound to it for ever: what
 * comes back is a reading, and the domain decides whether it holds a card.
 */
final readonly class SymfonyAiConventionsModel implements ConventionsModel
{
    public function __construct(
        private AiConfiguration $configuration,
        #[Autowire(service: 'ai.agent.conventions')] private AgentInterface $agent,
        private LoggerInterface $logger,
    ) {
    }

    public function read(string $sentence, Lang $lang): Reading
    {
        if (null === $this->configuration->settings()) {
            throw new ConventionsModelFailed('The AI is off.');
        }
        try {
            $messages = new MessageBag(Message::forSystem(ConventionsPrompt::system()), Message::ofUser(ConventionsPrompt::sentence($sentence, $lang)));
            $draft = $this->agent->call($messages, ['response_format' => CardDraft::class])->asObject(CardDraft::class);
        } catch (\Throwable $e) {
            // the platform's words may quote the request: they go to the log, not to the player
            $this->logger->error('The conventions model gave no reading: {error}', ['error' => $e->getMessage(), 'exception' => $e::class]);

            throw new ConventionsModelFailed('The model gave no reading.', previous: $e);
        }

        return new Reading(
            $draft->understood,
            ['open' => $draft->open, 'jack' => $draft->jack, 'nine' => $draft->nine, 'threeTrumps' => $draft->threeTrumps, 'belote' => $draft->belote, 'ace' => $draft->ace, 'aces' => $draft->aces],
            array_values(array_filter($draft->unplaced, \is_string(...))),
        );
    }
}
