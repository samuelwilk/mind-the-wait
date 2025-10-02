<?php

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__.'/src', __DIR__.'/config', __DIR__.'/tests', __DIR__.'/bin', __DIR__.'/public'])
    ->exclude(['var', 'vendor', 'migrations']); // keep migrations as generated
// ->name('*.php')

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__.'/.php-cs-fixer.cache')
    ->setIndent('    ')
    ->setLineEnding("\n")
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,        // ✅ enable risky rule set

        // Safer “risky” picks for strictness/clarity:
        'no_alias_functions' => true,
        'no_alias_language_construct_call' => true,
        'no_unreachable_default_argument_value' => true,
        'no_unset_on_property' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'self_static_accessor' => true,
        'strict_comparison' => true,      // use ===/!== when possible
        'strict_param' => true,           // stricter internal function params
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],

        // Style/usability
        'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
        'global_namespace_import' => ['import_classes' => null, 'import_functions' => true, 'import_constants' => true],
        'no_unused_imports' => true,
        'single_quote' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => ['default' => 'align_single_space_minimal'],
        'yoda_style' => false,

        // Opt-out of the spiciest risky rules for now:
        'declare_strict_types' => false,  // don’t auto-insert; add per-file/gradually
        'mb_str_functions' => false,      // don’t force mb_* yet
    ])
    ->setFinder($finder);
