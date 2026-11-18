<?php

declare(strict_types=1);

namespace Kabbout\Game\Application\Table;

/**
 * What a table answers when it will not do what was asked. Its message is written for whoever asked,
 * a person or a model, and may be shown as it is. Nothing has changed at the table.
 */
interface TableRefusal extends \Throwable
{
}
