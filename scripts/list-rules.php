#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\PrettyPHP\Contract\BlockRule;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Contract\Rule;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Formatter;
use Salient\Cli\CliApplication;
use Salient\Core\Facade\Console;
use Salient\Utility\Arr;
use Salient\Utility\Get;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * @param array<int,array<array{rule:class-string<Rule>,is_mandatory:bool,is_default:bool,pass:int,method:string,priority:int}>> $array
 * @param-out array<int,array<array{rule:class-string<Rule>,is_mandatory:bool,is_default:bool,pass:int,method:string,priority:int}>> $array
 * @param class-string<Rule> $rule
 */
function maybeAddRule(
    array &$array,
    int $pass,
    string $rule,
    bool $isMandatory,
    bool $isDefault,
    string $method,
    bool $reportDisabled = false
): void {
    $priority = $rule::getPriority($method);
    if ($priority !== null) {
        $array[$priority][] = [
            'rule' => $rule,
            'is_mandatory' => $isMandatory,
            'is_default' => $isDefault,
            'pass' => $pass,
            'method' => $method,
            'priority' => $priority,
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

foreach (Arr::extend(Formatter::DEFAULT_RULES, ...Formatter::OPTIONAL_RULES) as $rule) {
    $isMandatory = !in_array($rule, Formatter::OPTIONAL_RULES, true);
    $isDefault = in_array($rule, Formatter::DEFAULT_RULES, true);
    if (is_a($rule, TokenRule::class, true)) {
        maybeAddRule($mainLoop, 1, $rule, $isMandatory, $isDefault, TokenRule::PROCESS_TOKENS, true);
    }
    if (is_a($rule, ListRule::class, true)) {
        maybeAddRule($mainLoop, 1, $rule, $isMandatory, $isDefault, ListRule::PROCESS_LIST, true);
    }
    if (is_a($rule, BlockRule::class, true)) {
        maybeAddRule($blockLoop, 2, $rule, $isMandatory, $isDefault, BlockRule::PROCESS_BLOCK, true);
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
foreach ($rules as $r) {
    $rows[] = [
        '`' . Get::basename($r['rule']) . '`' . (
            $r['appearance'] === null ? '' : ' (' . $r['appearance'] . ')'
        ),
        $r['is_mandatory'] ? 'Y' : '-',
        $r['is_default'] && !$r['is_mandatory'] ? 'Y' : '-',
        $r['pass'],
        '`' . $r['method'] . '()`',
        $r['priority'],
    ];
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
