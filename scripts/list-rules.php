#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\PrettyPHP\Contract\BlockRule;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Contract\Rule;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Formatter;
use Salient\Cli\CliApplication;
use Salient\Core\Facade\Console;
use Salient\PHPDoc\PHPDoc;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Str;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * @param array<int,array<array{rule:class-string<Rule>,is_mandatory:bool,is_default:bool,pass:int,method:string,priority:int,php_doc:PHPDoc|null}>> $array
 * @param-out array<int,array<array{rule:class-string<Rule>,is_mandatory:bool,is_default:bool,pass:int,method:string,priority:int,php_doc:PHPDoc|null}>> $array
 * @param class-string<Rule> $rule
 * @param array<class-string<Rule>,array<string|null>>|null $callbackDocs
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
        $array[$priority][] = [
            'rule' => $rule,
            'is_mandatory' => $isMandatory,
            'is_default' => $isDefault,
            'pass' => $pass,
            'method' => $method,
            'priority' => $priority,
            'php_doc' => $phpDoc ?? null,
        ];
        return;
    }
    if ($reportDisabled) {
        Console::warn('Rule is disabled:', $rule);
    }
}

$app = new CliApplication(dirname(__DIR__));
Console::registerStderrTarget();

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

$rows = [['Rule', 'Mandatory?', 'Default?', 'Pass', 'Method', 'Priority']];
$docs = [];
foreach ($rules as $r) {
    $method = $r['method'] === Rule::CALLBACK
        ? '_`callback`_'
        : '`' . $r['method'] . '()`';
    $rows[] = [
        ($heading = '`' . Get::basename($r['rule']) . '`') . (
            $r['appearance'] === null ? '' : ' (' . $r['appearance'] . ')'
        ),
        $r['is_mandatory'] ? 'Y' : '-',
        $r['is_default'] && !$r['is_mandatory'] ? 'Y' : '-',
        $r['pass'],
        $method,
        $r['priority'],
    ];

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
        continue;
    }
    if ($description === null) {
        continue;
    }
    $docs[] = '## ' . $heading . (
        $r['appearance'] === null
            ? ''
            : ' (call ' . $r['appearance'] . ': ' . $method . ')'
    );
    $docs[] = $description;
}

foreach ($rows as $row) {
    foreach ($row as $i => $column) {
        $widths[$i] ??= 0;
        $widths[$i] = max($widths[$i], strlen((string) $column));
    }
}

$row = [];
foreach ($widths as $width) {
    $row[] = str_repeat('-', $width);
}
array_splice($rows, 1, 0, [$row]);

foreach ($rows as $row) {
    printf('|');
    foreach ($row as $i => $column) {
        printf(" %-{$widths[$i]}s |", $column);
    }
    printf("\n");
}

if ($docs) {
    printf("\n");
    printf("%s\n", implode("\n\n", $docs));
}
