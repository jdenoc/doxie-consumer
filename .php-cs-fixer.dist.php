<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->ignoreVCSIgnored(true)
    ->exclude([
        'docs',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@PHP83Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'function_declaration'=>['closure_function_spacing' => 'none'],
        'no_blank_lines_after_class_opening'=>false,
        'braces_position'=>[
            'functions_opening_brace'=>'same_line',
            'classes_opening_brace'=>'same_line',
        ]
    ])
    ->setFinder($finder)
    ;
