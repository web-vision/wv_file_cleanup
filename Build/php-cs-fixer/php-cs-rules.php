<?php
/**
 *  $ php-cs-fixer fix --config .php-cs-rules.php
 *
 * inside the TYPO3 directory. Warning: This may take up to 10 minutes.
 *
 * For more information read:
 *     https://www.php-fig.org/psr/psr-2/
 *     https://cs.sensiolabs.org
 */
if (PHP_SAPI !== 'cli') {
    die('This script supports command line usage only. Please check your command.');
}

$finder = PhpCsFixer\Finder::create()
    ->ignoreVCSIgnored(true)
    ->exclude([
        '.Build/',
        'Build/',
        'var/',
    ])
    ->in(realpath(__DIR__ . '/../../'));

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP74Migration' => true,
        'general_phpdoc_annotation_remove' => ['annotations' => ['author']],
        '@DoctrineAnnotation' => true,
        // @todo: Switch to @PER-CS2.0 once php-cs-fixer's todo list is done: https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/issues/7247
        '@PER-CS1.0' => true,
        'array_indentation' => true,
        'array_syntax' => ['syntax' => 'short'],
        'cast_spaces' => ['space' => 'none'],
        // @todo: Can be dropped once we enable @PER-CS2.0
        'concat_space' => ['spacing' => 'one'],
        'declare_equal_normalize' => ['space' => 'none'],
        'declare_parentheses' => true,
        'dir_constant' => true,
        // @todo: Can be dropped once we enable @PER-CS2.0
        'function_declaration' => ['closure_fn_spacing' => 'none'],
        'function_to_constant' => ['functions' => ['get_called_class', 'get_class', 'get_class_this', 'php_sapi_name', 'phpversion', 'pi']],
        'type_declaration_spaces' => true,
        'global_namespace_import' => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
        'list_syntax' => ['syntax' => 'short'],
        // @todo: Can be dropped once we enable @PER-CS2.0
        'method_argument_space' => true,
        'modernize_strpos' => true,
        'modernize_types_casting' => true,
        // @todo Enable this setting when PHP 8.1 is lowest supported php
        // 'multiline_promoted_properties' => ['keep_blank_lines' => true, 'minimum_number_of_parameters' => 1],
        'native_function_casing' => true,
        //        'native_function_invocation' => [
        //            'include' => ['@compiler_optimized'],
        //            'scope' => 'all',
        //            'strict' => true,
        //        ],
        'no_alias_functions' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => true,
        'no_leading_namespace_whitespace' => true,
        'no_null_property_initialization' => true,
        'no_short_bool_cast' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_superfluous_elseif' => true,
        'no_trailing_comma_in_singleline' => true,
        'no_unneeded_control_parentheses' => true,
        'no_unused_imports' => true,
        'no_useless_else' => true,
        'no_useless_nullsafe_operator' => true,
        'nullable_type_declaration' => ['syntax' => 'question_mark'],
        'nullable_type_declaration_for_default_null_value' => true,
        // @todo enable this after pending-pull-requests have been applied
        // 'ordered_class_elements' => ['order' => ['use_trait', 'case', 'constant', 'property']],
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'php_unit_construct' => ['assertions' => ['assertEquals', 'assertSame', 'assertNotEquals', 'assertNotSame']],
        'php_unit_data_provider_method_order' => ['placement' => 'before'],
        'php_unit_data_provider_static' => ['force' => true],
        'php_unit_mock_short_will_return' => true,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'this'],
        'php_unit_internal_class' => ['types' => ['abstract', 'normal', 'final']],
        'php_unit_method_casing' => ['case' => 'camel_case'],
        'php_unit_test_annotation' => ['style' => 'annotation'],
        'php_unit_mock' => ['target' => '5.5'],
        'php_unit_set_up_tear_down_visibility' => true,
        'php_unit_strict' => true,
        'php_unit_assert_new_names' => true,
        'phpdoc_no_access' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_no_package' => true,
        'phpdoc_scalar' => true,
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'phpdoc_types_order' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
        'return_type_declaration' => ['space_before' => 'none'],
        'single_quote' => true,
        'single_space_around_construct' => true,
        'single_line_comment_style' => ['comment_types' => ['hash']],
        // @todo: Can be dropped once we enable @PER-CS2.0
        'single_line_empty_body' => true,
        'strict_param' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
        'protected_to_private' => true,
        'self_static_accessor' => true,
        'whitespace_after_comma_in_array' => ['ensure_single_space' => true],
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
    ])
    ->setFinder($finder);
