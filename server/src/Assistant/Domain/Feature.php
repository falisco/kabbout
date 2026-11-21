<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Domain;

/** What the AI does around the table. It never plays the robots' cards: it explains, listens and referees. */
enum Feature: string
{
    /** "What did I do wrong?", answered from the engine's analysis of the deal */
    case Coach = 'coach';
    /** a player's sentence about how the pair bids becomes a conventions card the engine bids by */
    case Conventions = 'conventions';
    /** questions about the rules and the count */
    case Referee = 'referee';
    /** a language model in a seat, measured against the robots */
    case AgentSeat = 'agent_seat';

    /** Whether this version holds the feature. Each one turns true with the step that builds it. */
    public function shipped(): bool
    {
        return match ($this) {
            self::Coach, self::Conventions => true,
            self::Referee, self::AgentSeat => false,
        };
    }

    public function stateWith(?AiSettings $settings): FeatureState
    {
        return FeatureState::of($this->shipped(), null !== $settings);
    }
}
