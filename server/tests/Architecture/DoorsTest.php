<?php

declare(strict_types=1);

namespace Kabbout\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * One capability, several doors: an HTTP controller, an MCP tool and a tool of the coach's agent map
 * what they are asked to a capability of an Application layer, and its answer back. This reads every
 * door and fails when one starts doing the work itself: it holds no capability, or it reaches past the
 * Application layer to a port (the engine, the analysis, a store, a count, the model).
 *
 * What a door does with an answer on the way out (a status, a wording, the names of the cards) is
 * mapping and is not looked at here; which context it may name is LayersTest's.
 */
final class DoorsTest extends TestCase
{
    private const string SRC = __DIR__.'/../../src';
    /** where the doors are: what answers HTTP, what answers MCP, and the tools the coach's agent calls */
    private const array DOORS = ['*/UserInterface/Http/*Controller.php', '*/UserInterface/Mcp/*Tools.php', 'Assistant/Infrastructure/Ai/CoachTools.php'];
    /** ports: what a capability stands on, and no door may hold */
    private const array PORTS = [
        'Kabbout\Game\Domain\Engine',
        'Kabbout\Game\Domain\DealAnalysis',
        'Kabbout\Game\Application\Table\Tables',
        'Kabbout\Game\Application\Table\TableAllowance',
        'Kabbout\Identity\Domain\Players',
        'Kabbout\Assistant\Application\Coach\CoachModel',
        'Kabbout\Assistant\Application\Coach\CoachAllowance',
        'Kabbout\Assistant\Application\Coach\CoachMemory',
    ];
    /** a door that answers from nothing but the framework: it says what went wrong, and holds no capability */
    private const array NO_CAPABILITY = ['Api/UserInterface/Http/ErrorController.php'];

    /** @return iterable<string, array{string}> */
    public static function doors(): iterable
    {
        foreach (self::DOORS as $pattern) {
            foreach ((array) glob(self::SRC.'/'.$pattern) as $file) {
                \assert(\is_string($file));
                yield substr($file, \strlen(self::SRC) + 1) => [substr($file, \strlen(self::SRC) + 1)];
            }
        }
    }

    public function testEveryKindOfDoorIsRead(): void
    {
        $read = array_keys(iterator_to_array(self::doors()));

        foreach (['Game/UserInterface/Mcp/TableTools.php', 'Game/UserInterface/Mcp/ReviewTools.php', 'Assistant/UserInterface/Http/CoachController.php', 'Identity/UserInterface/Http/EnterController.php', 'Assistant/Infrastructure/Ai/CoachTools.php'] as $door) {
            self::assertContains($door, $read);
        }
    }

    #[DataProvider('doors')]
    public function testADoorMapsToACapabilityAndDoesNoWorkOfItsOwn(string $path): void
    {
        $ours = self::namesOf((string) file_get_contents(self::SRC.'/'.$path));

        self::assertSame([], array_values(array_intersect($ours, self::PORTS)), \sprintf('%s holds a port: that is a capability\'s to hold.', $path));
        if (!\in_array($path, self::NO_CAPABILITY, true)) {
            self::assertNotSame([], preg_grep('/^Kabbout\\\\\w+\\\\Application\\\\/', $ours), \sprintf('%s holds no capability of an Application layer.', $path));
        }
    }

    public function testTheRuleSeesAPortHoweverItIsNamed(): void
    {
        $names = self::namesOf('<?php namespace Kabbout\Game\UserInterface\Mcp; use Kabbout\Game\Domain\Engine; final class X { function __construct(private \Kabbout\Game\Application\Table\Tables $t) {} }');

        self::assertSame(['Kabbout\Game\Domain\Engine', 'Kabbout\Game\Application\Table\Tables'], array_values(array_intersect($names, self::PORTS)));
    }

    /** @return list<string> the names of ours a file writes, without their leading backslash */
    private static function namesOf(string $code): array
    {
        $names = [];
        foreach (\PhpToken::tokenize($code) as $token) {
            if ($token->is([\T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED]) && str_starts_with(ltrim($token->text, '\\'), 'Kabbout\\')) {
                $names[] = ltrim($token->text, '\\');
            }
        }

        return $names;
    }
}
