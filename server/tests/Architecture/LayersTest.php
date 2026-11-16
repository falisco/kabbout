<?php

declare(strict_types=1);

namespace Kabbout\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The dependency rule of the server, read from every class name written in server/src: imported, written in
 * full in the code or in an attribute. The code is cut by bounded context (Game, Assistant), then by layer:
 * - Domain knows its own context's domain and PHP itself: no framework, no other layer, no other context;
 * - Application knows domains and applications, and nothing else;
 * - a context never reaches into the adapters (Infrastructure, UserInterface) of another one.
 */
final class LayersTest extends TestCase
{
    private const string SRC = __DIR__.'/../../src';
    private const array LAYERS = ['Domain', 'Application', 'Infrastructure', 'UserInterface'];
    /** the files that belong to no context */
    private const array OUTSIDE = ['Kernel.php'];

    /** @return iterable<string, array{string}> every PHP file of server/src, by its path from there */
    public static function files(): iterable
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::SRC, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $path = substr($file->getPathname(), \strlen(self::SRC) + 1);
            if (!\in_array($path, self::OUTSIDE, true)) {
                yield $path => [$path];
            }
        }
    }

    #[DataProvider('files')]
    public function testAFileOnlyNamesWhatItsLayerMayKnow(string $path): void
    {
        $parts = explode('/', $path);
        self::assertGreaterThanOrEqual(3, \count($parts), \sprintf('%s is in no context and no layer.', $path));
        [$context, $layer] = $parts;
        self::assertContains($layer, self::LAYERS, \sprintf('%s is in no known layer.', $path));

        foreach (self::namesIn($path) as $name) {
            $named = explode('\\', $name);
            $ours = 'Kabbout' === $named[0];
            [$otherContext, $otherLayer] = $ours ? [$named[1] ?? '', $named[2] ?? ''] : ['', ''];

            if ('Domain' === $layer) {
                self::assertTrue($ours && $context === $otherContext && 'Domain' === $otherLayer, \sprintf('%s is domain code and names %s.', $path, $name));
            }
            if ('Application' === $layer) {
                self::assertTrue($ours && \in_array($otherLayer, ['Domain', 'Application'], true), \sprintf('%s is application code and names %s.', $path, $name));
            }
            if ($ours && $context !== $otherContext) {
                self::assertContains($otherLayer, ['Domain', 'Application'], \sprintf('%s reaches into the adapters of another context: %s.', $path, $name));
            }
        }
    }

    public function testTheRuleSeesNamesHoweverTheyAreWritten(): void
    {
        $names = self::namesOf('<?php namespace A\B; use C\D; use function E\f; #[\G\H] class X { function m(): \I\J { return new K\L(); } }');

        self::assertSame(['A\B', 'C\D', 'E\f', 'G\H', 'I\J', 'K\L'], $names);
    }

    /** @return list<string> */
    private static function namesIn(string $path): array
    {
        return self::namesOf((string) file_get_contents(self::SRC.'/'.$path));
    }

    /**
     * Every name with a namespace in it. A name of one word is PHP's own (\DomainException) or was imported,
     * and its import is one of the names returned. A group import would hide its second half: the coding
     * standard forbids them, and this fails on one.
     *
     * @return list<string>
     */
    private static function namesOf(string $code): array
    {
        $names = [];
        foreach (\PhpToken::tokenize($code) as $token) {
            if ($token->is([\T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED]) && str_contains(ltrim($token->text, '\\'), '\\')) {
                $names[] = ltrim($token->text, '\\');
            }
            self::assertFalse($token->is(\T_NAME_RELATIVE), 'A name relative to its namespace hides where it points.');
        }
        self::assertStringNotContainsString('\{', $code, 'A group import hides the names it brings.');

        return $names;
    }
}
