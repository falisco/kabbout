<?php

declare(strict_types=1);

namespace Kabbout\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * One capability, several doors: an HTTP controller, a console command, an MCP tool and a tool of the
 * coach's agent map what they are asked to a capability of an Application layer, and its answer back.
 * This reads every file of every UserInterface layer, and the coach's tools, and fails when one starts
 * doing the work itself:
 *
 * - it holds a port (the engine, the analysis, a store, a count, the model, the clock): that is a
 *   capability's to hold. The ports are not listed here: they are every interface of a Domain or an
 *   Application layer, read from the code, so a new one is covered the day it is written;
 * - it is a controller, a command or a set of tools and names no capability at all (the two doors that
 *   only say who is asking, or whether the AI is on, name the interface they ask instead);
 * - it is a set of MCP tools and decides something: an `if`, a `match`, a loop, a `?:`, a `??`. Those
 *   files are held to "nothing but a mapping" to the letter, which they can be: who is asking and what
 *   a refusal says are Door's, said once for all of them.
 *
 * What an HTTP controller or the coach's tools do with an answer on the way out (a status, a wording,
 * the names of the cards) is mapping, and has conditions: it is not looked at here. Which context a
 * file may name is LayersTest's, which also refuses the group imports and relative names that would
 * hide a name from this test.
 */
final class DoorsTest extends TestCase
{
    private const string SRC = __DIR__.'/../../src';
    private const string COACH_TOOLS = 'Assistant/Infrastructure/SymfonyAi/Coach/CoachTools.php';
    /**
     * Interfaces a door is meant to ask, each for what it says here.
     * CurrentPlayer: "who is asking" is the question every door starts with.
     * AiConfiguration: whether the AI is on, which a door says before it asks anything of it.
     */
    private const array ASKED_BY_DOORS = ['Kabbout\Identity\Application\CurrentPlayer', 'Kabbout\Assistant\Domain\Settings\AiConfiguration'];
    /** what decides: none of it in a file of MCP tools */
    private const array DECIDES = [\T_IF, \T_ELSE, \T_ELSEIF, \T_SWITCH, \T_MATCH, \T_FOR, \T_FOREACH, \T_WHILE, \T_DO, \T_COALESCE, \T_COALESCE_EQUAL, \T_TRY, \T_CATCH];

    /** @return iterable<string, array{string}> every file of a UserInterface layer, and the coach's tools */
    public static function doors(): iterable
    {
        foreach (self::files() as $path) {
            if (self::COACH_TOOLS === $path || 1 === preg_match('#^\w+/UserInterface/#', $path)) {
                yield $path => [$path];
            }
        }
    }

    /** @return iterable<string, array{string}> the doors that are an entrance by themselves */
    public static function entrances(): iterable
    {
        foreach (self::doors() as $path => $door) {
            if (1 === preg_match('/(Controller|Command|Tools)\.php$/', $path) && 'Api/UserInterface/Http/ErrorController.php' !== $path) {
                yield $path => $door;
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function mcpTools(): iterable
    {
        foreach (self::doors() as $path => $door) {
            if (1 === preg_match('#/UserInterface/Mcp/\w+Tools\.php$#', $path)) {
                yield $path => $door;
            }
        }
    }

    public function testEveryKindOfDoorIsRead(): void
    {
        $doors = array_keys(iterator_to_array(self::doors()));
        foreach (['Game/UserInterface/Mcp/TableTools.php', 'Game/UserInterface/Mcp/ReviewTools.php', 'Game/UserInterface/Mcp/Door.php', 'Assistant/UserInterface/Http/CoachController.php', 'Identity/UserInterface/Console/GuestCommand.php', self::COACH_TOOLS] as $door) {
            self::assertContains($door, $doors);
        }
        self::assertSame(['Game/UserInterface/Mcp/ReviewTools.php', 'Game/UserInterface/Mcp/TableTools.php'], array_keys(iterator_to_array(self::mcpTools())));
        // the ports are found, not listed: these are some of them, and the two a door may ask are not among them
        $ports = self::ports();
        foreach (['Kabbout\Game\Domain\Engine', 'Kabbout\Game\Domain\DealAnalysis', 'Kabbout\Game\Domain\Table\Tables', 'Kabbout\Game\Application\Table\TableAllowance', 'Kabbout\Game\Application\Table\Now', 'Kabbout\Identity\Domain\Players', 'Kabbout\Identity\Domain\Chance', 'Kabbout\Identity\Application\EntryAllowance', 'Kabbout\Assistant\Application\Coach\CoachModel'] as $port) {
            self::assertContains($port, $ports);
        }
        self::assertSame([], array_intersect(self::ASKED_BY_DOORS, $ports));
        // what a capability refuses with is no port: a door must name it to say it
        self::assertNotContains('Kabbout\Game\Application\Table\TableRefusal', $ports);
    }

    #[DataProvider('doors')]
    public function testADoorHoldsNoPort(string $path): void
    {
        $held = array_values(array_intersect(self::namesOf((string) file_get_contents(self::SRC.'/'.$path)), self::ports()));

        self::assertSame([], $held, \sprintf('%s holds a port: that is a capability\'s to hold.', $path));
    }

    #[DataProvider('entrances')]
    public function testAnEntranceMapsToACapability(string $path): void
    {
        $capabilities = array_filter(
            self::namesOf((string) file_get_contents(self::SRC.'/'.$path)),
            static fn (string $name): bool => \in_array($name, self::ASKED_BY_DOORS, true)
                || (1 === preg_match('/^Kabbout\\\\\w+\\\\Application\\\\/', $name) && method_exists($name, '__invoke')),
        );

        self::assertNotSame([], $capabilities, \sprintf('%s names no capability of an Application layer, and asks neither who is asking nor whether the AI is on.', $path));
    }

    #[DataProvider('mcpTools')]
    public function testAnMcpToolDecidesNothing(string $path): void
    {
        self::assertSame([], self::decisionsIn((string) file_get_contents(self::SRC.'/'.$path)), \sprintf('%s decides something: a tool maps one call to one capability.', $path));
    }

    public function testTheRulesSeeWhatTheyAreAfter(): void
    {
        $door = '<?php namespace Kabbout\Game\UserInterface\Mcp; use Kabbout\Game\Domain\Engine; use Kabbout\Game\Application\Table\Turn;
            final class X { function __construct(private \Kabbout\Game\Domain\Table\Tables $t) {}
            function newGame($level, $seat) { if (3 === $seat) { $level = "expert"; } return $seat ?: $level ?? match (true) { default => 1 }; } }';

        self::assertSame(['Kabbout\Game\Domain\Engine', 'Kabbout\Game\Domain\Table\Tables'], array_values(array_intersect(self::namesOf($door), self::ports())));
        self::assertSame(['if', '?', '??', 'match'], self::decisionsIn($door));
        // a value of an Application layer is no capability: a door that names only that maps to nothing
        self::assertSame([false, true], array_map(static fn (string $name): bool => method_exists($name, '__invoke'), ['Kabbout\Game\Application\Table\Turn', 'Kabbout\Game\Application\Table\OpenTable']));
        // a nullable type is no decision
        self::assertSame([], self::decisionsIn('<?php function bid(?int $value = null, #[Schema(pattern: X::Y)] ?Suit $suit = null): ?array { return null; }'));
    }

    /** @return list<string> the interfaces of the Domain and Application layers, less what a door is meant to ask and what marks an exception */
    private static function ports(): array
    {
        $ports = [];
        foreach (self::files() as $path) {
            if (1 !== preg_match('#^\w+/(Domain|Application)/#', $path)) {
                continue;
            }
            $name = 'Kabbout\\'.str_replace('/', '\\', substr($path, 0, -4));
            if (interface_exists($name) && !is_subclass_of($name, \Throwable::class) && !\in_array($name, self::ASKED_BY_DOORS, true)) {
                $ports[] = $name;
            }
        }

        return $ports;
    }

    /** @return list<string> the files of src, as paths from it */
    private static function files(): array
    {
        $paths = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::SRC, \FilesystemIterator::SKIP_DOTS)) as $file) {
            \assert($file instanceof \SplFileInfo);
            if ('php' === $file->getExtension()) {
                $paths[] = substr($file->getPathname(), \strlen(self::SRC) + 1);
            }
        }
        sort($paths);

        return $paths;
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

    /** @return list<string> every token of a file that decides something; a `?` that only says a type may be null is none */
    private static function decisionsIn(string $code): array
    {
        $tokens = array_values(array_filter(\PhpToken::tokenize($code), static fn (\PhpToken $token): bool => !$token->isIgnorable()));
        $found = [];
        foreach ($tokens as $at => $token) {
            $nullableType = '?' === $token->text && ($tokens[$at - 1] ?? null)?->is(['(', ',', ':', ']']) === true;
            if ($token->is(self::DECIDES) || ('?' === $token->text && !$nullableType)) {
                $found[] = $token->text;
            }
        }

        return $found;
    }
}
