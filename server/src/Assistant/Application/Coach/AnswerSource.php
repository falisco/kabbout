<?php

declare(strict_types=1);

namespace Kabbout\Assistant\Application\Coach;

/** Who wrote an answer of the coach. */
enum AnswerSource: string
{
    /** the language model, every number and card checked against its tools' results */
    case Model = 'model';
    /** sentences built from the engine's analysis, with no model */
    case Summary = 'summary';
}
