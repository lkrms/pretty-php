#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Contract\BlockRule;
use Lkrms\PrettyPHP\Contract\DeclarationRule;
use Lkrms\PrettyPHP\Contract\HasTokenNames;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Contract\Rule;
use Lkrms\PrettyPHP\Contract\StatementRule;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\IndexSpacing;
use Lkrms\PrettyPHP\Rule\OperatorSpacing;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\TokenIndex;
use Salient\Cli\CliApplication;
use Salient\Core\Facade\Console;
use Salient\PHPDoc\PHPDoc;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Reflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * @param array<int,array<array{rule:class-string<Rule>,is_mandatory:bool,is_default:bool,pass:int,method:string,priority:int,php_doc:PHPDoc|null,tokens:array<int,bool>|array{string}|null,declarations:array<int,bool>|array{'*'}|null}>> $array
 * @param class-string<Rule> $rule
 * @param array<class-string<Rule>,array<string|null>>|null $callbackDocs
 * @param-out array<int,array<array{rule:class-string<Rule>,is_mandatory:bool,is_default:bool,pass:int,method:string,priority:int,php_doc:PHPDoc|null,tokens:array<int,bool>|array{string}|null,declarations:array<int,bool>|array{'*'}|null}>> $array
 */
function maybeAddRule(
    array &$array,
    int $pass,
    string $rule,
    bool $isMandatory,
    bool $isDefault,
    string $method,
    bool $reportDisabled = false,
    ?array &$callbackDocs = null
): void {
    $priority = $rule::getPriority($method);
    if ($priority !== null) {
        if ($method !== Rule::CALLBACK) {
            $comment = (new ReflectionMethod($rule, $method))->getDocComment();
            if ($comment !== false) {
                $phpDoc = new PHPDoc($comment);
                if (
                    $callbackDocs !== null
                    && $phpDoc->hasTag('prettyphp-callback')
                ) {
                    foreach ($phpDoc->getTags()['prettyphp-callback'] as $tag) {
                        $callbackDocs[$rule][] = $tag->getDescription();
                    }
                }
            }
        }
        if ($method === TokenRule::PROCESS_TOKENS) {
            /** @var TokenIndex|null */
            static $idx;
            $idx ??= new TokenIndex();
            /** @var class-string<TokenRule> $rule */
            $tokens = $rule::getTokens($idx);
            if ($tokens === $idx->NotVirtual) {
                $tokens = ['* (except virtual)'];
            }
        } elseif ($method === DeclarationRule::PROCESS_DECLARATIONS) {
            static $all;
            $all ??= getAllDeclarationTypes();
            /** @var class-string<DeclarationRule> $rule */
            $declarations = $rule::getDeclarationTypes($all);
        }
        $array[$priority][] = [
            'rule' => $rule,
            'is_mandatory' => $isMandatory,
            'is_default' => $isDefault,
            'pass' => $pass,
            'method' => $method,
            'priority' => $priority,
            'php_doc' => $phpDoc ?? null,
            'tokens' => $tokens ?? null,
            'declarations' => $declarations ?? null,
        ];
        return;
    }
    if ($reportDisabled) {
        Console::warn('Rule is disabled:', $rule);
    }
}

/**
 * @return array<int,true>
 */
function getAllDeclarationTypes(): array
{
    /** @var int[] */
    $types = Reflect::getConstants(Type::class);
    return array_fill_keys($types, true);
}

/**
 * @param non-empty-array<non-empty-array<string|int>> $table
 */
function printTable(array $table): void
{
    foreach ($table as $row) {
        foreach ($row as $i => $column) {
            $widths[$i] ??= 0;
            $widths[$i] = max($widths[$i], strlen((string) $column));
        }
    }

    $row = [];
    foreach ($widths as $width) {
        $row[] = str_repeat('-', $width);
    }
    array_splice($table, 1, 0, [$row]);

    foreach ($table as $row) {
        printf('|');
        foreach ($row as $i => $column) {
            printf(" %-{$widths[$i]}s |", $column);
        }
        printf("\n");
    }
}

$app = new CliApplication(dirname(__DIR__));

$mainLoop = [];
$blockLoop = [];
$callback = [];
$beforeRender = [];
$callbackDocs = [];

foreach (Arr::extend(Formatter::DEFAULT_RULES, ...Formatter::OPTIONAL_RULES) as $rule) {
    $isMandatory = !in_array($rule, Formatter::OPTIONAL_RULES, true);
    $isDefault = in_array($rule, Formatter::DEFAULT_RULES, true);
    if (is_a($rule, TokenRule::class, true)) {
        maybeAddRule($mainLoop, 1, $rule, $isMandatory, $isDefault, TokenRule::PROCESS_TOKENS, true, $callbackDocs);
    }
    if (is_a($rule, ListRule::class, true)) {
        maybeAddRule($mainLoop, 1, $rule, $isMandatory, $isDefault, ListRule::PROCESS_LIST, true, $callbackDocs);
    }
    if (is_a($rule, StatementRule::class, true)) {
        maybeAddRule($mainLoop, 1, $rule, $isMandatory, $isDefault, StatementRule::PROCESS_STATEMENTS, true, $callbackDocs);
    }
    if (is_a($rule, DeclarationRule::class, true)) {
        maybeAddRule($mainLoop, 1, $rule, $isMandatory, $isDefault, DeclarationRule::PROCESS_DECLARATIONS, true, $callbackDocs);
    }
    if (is_a($rule, BlockRule::class, true)) {
        maybeAddRule($blockLoop, 2, $rule, $isMandatory, $isDefault, BlockRule::PROCESS_BLOCK, true, $callbackDocs);
    }
    maybeAddRule($callback, 3, $rule, $isMandatory, $isDefault, Rule::CALLBACK);
    maybeAddRule($beforeRender, 4, $rule, $isMandatory, $isDefault, Rule::BEFORE_RENDER);
}

ksort($mainLoop);
ksort($blockLoop);
ksort($callback);
ksort($beforeRender);
$rules = Arr::flatten(array_merge($mainLoop, $blockLoop, $callback, $beforeRender), 1);

$index = [];
foreach ($rules as $key => $rule) {
    $index[$rule['rule']][] = $key;
    $rules[$key]['appearance'] = null;
}

foreach ($index as $rule => $keys) {
    $count = count($keys);
    if ($count === 1) {
        continue;
    }
    $i = 1;
    foreach ($keys as $key) {
        $rules[$key]['appearance'] = $i++;
    }
}

$table1 = [['Rule', 'Mandatory?', 'Default?', 'Pass', 'Method', 'Priority']];
$table2 = [['Token', 'Rules']];
$table3 = [['Declaration', 'Rules']];
$docs = [];
$tokenRules = [];
$declarationRules = [];
foreach ($rules as $r) {
    $method = $r['method'] === Rule::CALLBACK
        ? '_`callback`_'
        : '`' . $r['method'] . '()`';
    $table1[] = [
        ($heading = '`' . Get::basename($r['rule']) . '`') . (
            $call = $r['appearance'] === null ? '' : ' (' . $r['appearance'] . ')'
        ),
        $r['is_mandatory'] ? 'Y' : '-',
        $r['is_default'] && !$r['is_mandatory'] ? 'Y' : '-',
        $r['pass'],
        $method,
        $r['priority'],
    ];

    $tokens = null;
    if (is_string($r['tokens'][0] ?? null)) {
        $tokenRules[$r['tokens'][0]][] = $heading;
        $tokens[] = $r['tokens'][0];
    } elseif ($r['tokens'] && $r['rule'] !== IndexSpacing::class) {
        $tokenIds = array_keys(array_filter($r['tokens']));
        sort($tokenIds, \SORT_NUMERIC);
        foreach ($tokenIds as $id) {
            $name = HasTokenNames::TOKEN_NAME[$id] ?? token_name($id);
            if ($r['rule'] !== OperatorSpacing::class) {
                $tokenRules[$name][] = $heading;
            }
            $tokens[] = $id < 256
                ? ($id === \T_BACKTICK ? '` ` `' : chr($id))
                : $name;
        }
    }

    $declarations = null;
    if ($r['declarations'] === ['*']) {
        $declarationRules['*'][] = $heading;
        $declarations[] = '*';
    } elseif ($r['declarations']) {
        foreach (array_keys(array_filter($r['declarations'])) as $id) {
            $name = Reflect::getConstantName(Type::class, $id);
            $name = ltrim($name, '_');
            $declarationRules[$name][] = $heading;
            $declarations[] = $name;
        }
    }

    if ($r['php_doc']) {
        /** @var PHPDoc */
        $phpDoc = $r['php_doc'];
        $description = $phpDoc->getDescription();
    } elseif (
        $r['method'] === Rule::CALLBACK
        && isset($callbackDocs[$r['rule']])
    ) {
        $description = Str::coalesce(
            Arr::implode("\n\n", $callbackDocs[$r['rule']]),
            null,
        );
    } else {
        $description = null;
    }
    $docs[] = '### ' . $heading . $call;
    $docs[] = '<small>(' . (
        $r['is_mandatory']
            ? 'mandatory'
            : (
                $r['is_default']
                    ? 'default'
                    : 'optional'
            )
    ) . ', ' . $method . ', priority ' . $r['priority'] . (
        ($with = $tokens ?? $declarations)
            ? ', ' . ($tokens ? 'tokens' : 'declarations')
                . ': `' . implode('` | `', $with) . '`'
            : ''
    ) . ')</small>';
    // Remove leading ">" after non-empty lines
    $description = Regex::replace('/(?<!\n)(\n\h*+)> ?/m', '$1', $description ?? 'Not documented.');
    $docs[] = Str::unwrap($description, "\n", false, true, true);
}

printTable($table1);

if ($docs) {
    array_unshift($docs, '## Descriptions');
    printf("\n");
    printf("%s\n", implode("\n\n", $docs));
}

ksort($tokenRules);
foreach ($tokenRules as $name => $rules) {
    sort($rules);
    $table2[] = ['`' . $name . '`', implode(', ', $rules)];
}

printf("\n");
printf("## `TokenRule` classes, by token\n\n");
printTable($table2);

ksort($declarationRules);
foreach ($declarationRules as $name => $rules) {
    sort($rules);
    $table3[] = ['`' . $name . '`', implode(', ', $rules)];
}

printf("\n");
printf("## `DeclarationRule` classes, by declaration type\n\n");
printTable($table3);
