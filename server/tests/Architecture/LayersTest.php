<?php

declare(strict_types=1);

namespace Kabbout\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The dependency rule of the server, read from the `use` lines of server/src.
 * The code is cut by bounded context (Game, Assistant), then by layer:
 * - Domain knows no framework and no other layer;
 * - Application knows the domain only;
 * - a context reaches another one through its Domain or its Application, never through its adapters.
 */
final class LayersTest extends TestCase
{
    private const string SRC = __DIR__.'/../../src';
    private const array OUTER_LAYERS = ['Application', 'Infrastructure', 'UserInterface'];

    /** @return iterable<string, array{string, string, string, list<string>}> a file, its context, its layer, what it imports */
    public static function files(): iterable
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::SRC, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $path = substr($file->getPathname(), \strlen(self::SRC) + 1);
            $parts = explode('/', $path);
            if (\count($parts) < 3) {
                continue; // the kernel, which belongs to no context
            }
            preg_match_all('/^use ([^;]+);/m', (string) file_get_contents($file->getPathname()), $found);
            yield $path => [$path, $parts[0], $parts[1], $found[1]];
        }
    }

    /** @param list<string> $imports */
    #[DataProvider('files')]
    public function testAFileOnlyImportsWhatItsLayerMayKnow(string $path, string $context, string $layer, array $imports): void
    {
        foreach ($imports as $import) {
            $parts = explode('\\', $import);
            $ours = 'Kabbout' === $parts[0];
            $otherContext = $ours ? $parts[1] : null;
            $otherLayer = $ours ? ($parts[2] ?? '') : null;

            if ('Domain' === $layer) {
                self::assertTrue($ours && 'Domain' === $otherLayer && $context === $otherContext, \sprintf('%s is domain code and imports %s.', $path, $import));
            }
            if ('Application' === $layer) {
                self::assertTrue($ours && \in_array($otherLayer, ['Domain', 'Application'], true), \sprintf('%s is application code and imports %s.', $path, $import));
            }
            if ($ours && $context !== $otherContext) {
                self::assertNotContains($otherLayer, ['Infrastructure', 'UserInterface'], \sprintf('%s reaches into the adapters of another context: %s.', $path, $import));
            }
        }
        self::assertContains($layer, ['Domain', ...self::OUTER_LAYERS], \sprintf('%s is in no known layer.', $path));
    }
}
