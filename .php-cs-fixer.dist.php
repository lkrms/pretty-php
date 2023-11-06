<?php

$customTokens = [
    'T_NULL',
    'T_END_ALT_SYNTAX',
    'T_ATTRIBUTE_COMMENT',
    'T_LOGICAL_NOT',
    'T_DOUBLE_QUOTE',
    'T_DOLLAR',
    'T_MOD',
    'T_AND',
    'T_OPEN_PARENTHESIS',
    'T_CLOSE_PARENTHESIS',
    'T_MUL',
    'T_PLUS',
    'T_COMMA',
    'T_MINUS',
    'T_CONCAT',
    'T_DIV',
    'T_COLON',
    'T_SEMICOLON',
    'T_SMALLER',
    'T_EQUAL',
    'T_GREATER',
    'T_QUESTION',
    'T_AT',
    'T_OPEN_BRACKET',
    'T_CLOSE_BRACKET',
    'T_XOR',
    'T_BACKTICK',
    'T_OPEN_BRACE',
    'T_OR',
    'T_CLOSE_BRACE',
    'T_NOT',
];

$finder = (new PhpCsFixer\Finder())
              ->in([
                  __DIR__ . '/src',
                  __DIR__ . '/scripts',
                  __DIR__ . '/tests/unit',
                  __DIR__ . '/tools/apigen/src',
              ])
              ->append([
                  __DIR__ . '/bin/pretty-php',
                  __DIR__ . '/bootstrap.php',
              ]);

return (new PhpCsFixer\Config())
           ->setRules([
               'fully_qualified_strict_types' => true,
               'is_null' => true,
               'native_constant_invocation' => ['include' => $customTokens],
               'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
               'no_unneeded_import_alias' => true,
               'no_unused_imports' => true,
               'phpdoc_no_useless_inheritdoc' => true,
               'single_import_per_statement' => true,
               'yoda_style' => ['equal' => false, 'identical' => false],
           ])
           ->setFinder($finder)
           ->setRiskyAllowed(true);
