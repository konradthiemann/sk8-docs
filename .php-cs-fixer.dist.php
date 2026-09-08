<?php

declare(strict_types=1);

/*
 * Code style per ADR-007: @Symfony plus @PER-CS. @PER-CS is applied last and therefore
 * wins where the two disagree (most visibly: spaces around the concatenation dot).
 */
$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'vendor', 'public/assets'])
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
        'config/preload.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@PER-CS' => true,
        'declare_strict_types' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
    ])
    ->setFinder($finder)
;
