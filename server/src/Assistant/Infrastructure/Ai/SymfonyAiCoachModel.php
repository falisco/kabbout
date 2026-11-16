<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Infrastructure\Ai;

use Kabbout\Assistant\Application\Coach\CoachBrief;
use Kabbout\Assistant\Application\Coach\CoachModel;
use Kabbout\Assistant\Application\Coach\CoachModelFailed;
use Kabbout\Assistant\Application\Coach\Rejected;
use Kabbout\Assistant\Domain\AiConfiguration;
use Kabbout\Assistant\Domain\Grounding\Facts;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The coach's model: the agent `coach` of the Symfony AI bundle (config/packages/ai.yaml), which holds
 * the platform, the model, the five tools of CoachTools and the limit on how often it may call them.
 * What changes with each question is given here: the deal, through CoachConversation, and the system
 * prompt, written for the player's seat and language.
 *
 * A second question about the same brief (the guard rejected the first answer) goes on with the same
 * conversation, tool results included, so the model corrects its answer instead of starting again.
 */
final class SymfonyAiCoachModel implements CoachModel
{
    /** @var \WeakMap<CoachBrief, MessageBag> the messages so far, for as long as their brief lives */
    private \WeakMap $messages;

    public function __construct(
        private readonly AiConfiguration $configuration,
        #[Autowire(service: 'ai.agent.coach')] private readonly AgentInterface $agent,
        private readonly CoachConversation $conversation,
        private readonly LoggerInterface $logger,
    ) {
        $this->messages = new \WeakMap();
    }

    public function answer(CoachBrief $brief, Facts $facts, ?Rejected $rejected = null): string
    {
        $this->conversation->open($brief, $facts);
        try {
            if (null === $this->configuration->settings()) {
                throw new CoachModelFailed('The AI is off.');
            }
            $execution = $this->agent->call($this->messagesFor($brief, $rejected));
            $text = $execution->asText();
            $this->messages[$brief] = $execution->getMessages();

            return $text;
        } catch (CoachModelFailed $failed) {
            throw $failed;
        } catch (\Throwable $e) {
            // the platform's words may quote the request: they go to the log, not to the player.
            // An error, not a warning: with a wrong key or a model the platform does not run, every answer ends here.
            $this->logger->error('The coach\'s model gave no answer: {error}', ['error' => $e->getMessage(), 'exception' => $e::class]);

            throw new CoachModelFailed('The model gave no answer.', previous: $e);
        } finally {
            $this->conversation->close();
        }
    }

    private function messagesFor(CoachBrief $brief, ?Rejected $rejected): MessageBag
    {
        if (null === $rejected) {
            return self::opening($brief);
        }
        $correction = Message::ofUser(CoachPrompt::correction($rejected, $brief->lang));
        $before = $this->messages[$brief] ?? null;

        return null !== $before
            ? $before->with($correction)
            : self::opening($brief)->with(Message::ofAssistant($rejected->answer))->with($correction);
    }

    /** the rules, what was already said about the deal, and the question */
    private static function opening(CoachBrief $brief): MessageBag
    {
        $messages = new MessageBag(Message::forSystem(CoachPrompt::system($brief)));
        foreach ($brief->earlier as $exchange) {
            $messages = $messages->with(Message::ofUser($exchange->question))->with(Message::ofAssistant($exchange->answer));
        }

        return $messages->with(Message::ofUser($brief->question));
    }
}
