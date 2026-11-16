<?php

declare(strict_types=1);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
    ])
    ->setCacheFile(__DIR__.'/var/php-cs-fixer.cache')
    ->setFinder((new PhpCsFixer\Finder())->in([__DIR__.'/src', __DIR__.'/tests'])->append([__FILE__]));
