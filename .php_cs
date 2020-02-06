<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/test')
;

return PhpCsFixer\Config::create()
    ->setRules([
        '@PSR2' => true,
        '@PHPUnit60Migration:risky' => true,
        '@PHPUnit75Migration:risky' => true,
        'no_whitespace_in_blank_line' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;