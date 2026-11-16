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
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * The coach's model, as a Symfony AI agent: the platform and the model of the settings, the five tools
 * of the deal, and a limit on how often it may call them. The agent is built for each question: the
 * settings are read at run time and the tools belong to one deal.
 *
 * A second question about the same brief (the guard rejected the first answer) goes on with the same
 * conversation, tool results included, so the model corrects its answer instead of starting again.
 */
final class SymfonyAiCoachModel implements CoachModel
{
    /** rounds of tool calls in one answer: the five tools, and a few closer looks */
    public const int MAX_TOOL_CALLS = 8;

    /** @var \WeakMap<CoachBrief, MessageBag> the conversation so far, for as long as its brief lives */
    private \WeakMap $conversations;

    public function __construct(
        private readonly AiConfiguration $configuration,
        private readonly AiPlatformFactory $platforms,
        private readonly CoachToolsFactory $tools,
        private readonly LoggerInterface $logger,
    ) {
        $this->conversations = new \WeakMap();
    }

    public function answer(CoachBrief $brief, Facts $facts, ?Rejected $rejected = null): string
    {
        try {
            $settings = $this->configuration->settings() ?? throw new CoachModelFailed('The AI is off.');
            $agent = new Agent(
                $this->platforms->create($settings),
                '' !== $settings->model ? $settings->model : throw new CoachModelFailed('The AI settings name no model.'),
                name: 'coach',
                toolbox: new FaultTolerantToolbox(new Toolbox([$this->tools->for($brief, $facts)])),
                maxToolCalls: self::MAX_TOOL_CALLS,
            );
            $execution = $agent->call($this->messages($brief, $rejected));
            $text = $execution->asText();
            $this->conversations[$brief] = $execution->getMessages();

            return $text;
        } catch (CoachModelFailed $failed) {
            throw $failed;
        } catch (\Throwable $e) {
            // the platform's words may quote the request: they go to the log, not to the player
            $this->logger->warning('The coach\'s model gave no answer: {error}', ['error' => $e->getMessage(), 'exception' => $e::class]);

            throw new CoachModelFailed('The model gave no answer.', previous: $e);
        }
    }

    private function messages(CoachBrief $brief, ?Rejected $rejected): MessageBag
    {
        if (null === $rejected) {
            return new MessageBag(Message::forSystem(CoachPrompt::system($brief)), Message::ofUser($brief->question));
        }
        $correction = Message::ofUser(CoachPrompt::correction($rejected, $brief->lang));
        $before = $this->conversations[$brief] ?? null;

        return null !== $before
            ? $before->with($correction)
            : new MessageBag(Message::forSystem(CoachPrompt::system($brief)), Message::ofUser($brief->question), Message::ofAssistant($rejected->answer), $correction);
    }
}
