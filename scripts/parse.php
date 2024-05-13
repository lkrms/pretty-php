#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\PrettyPHP\Token\Token;
use Lkrms\PrettyPHP\Formatter;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;
use Salient\Cli\CliApplication;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\File;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new CliApplication(dirname(__DIR__));
Console::registerStderrTarget(true);

/** @var bool */
$tokenize = false;
/** @var bool */
$tokenizeForComparison = false;
/** @var bool */
$parseWithPhpParser = false;
/** @var bool */
$dump = false;

$args = array_slice($argv, 1);

foreach ([
    '--tokenize' => &$tokenize,
    '--tokenize-for-comparison' => &$tokenizeForComparison,
    '--parse-with-php-parser' => &$parseWithPhpParser,
    '--dump' => &$dump,
] as $arg => &$value) {
    if (($key = array_search($arg, $args, true)) !== false) {
        array_splice($args, $key, 1);
        $value = true;
    }
}
unset($value);

$code = File::getContents($args[0] ?? 'php://stdin');

if ($tokenize || $tokenizeForComparison) {
    $method = $tokenize ? 'tokenize' : 'tokenizeForComparison';
    $tokens = Token::$method($code, \TOKEN_PARSE);

    Console::summary(
        sprintf('Input tokenized by %s::%s()', Token::class, $method),
        'without errors',
        true,
    );

    exit;
}

if ($parseWithPhpParser) {
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $ast = $parser->parse($code);

    Console::summary('Input parsed by ' . get_class($parser), 'without errors', true);

    if ($dump && $ast !== null) {
        echo (new NodeDumper())->dump($ast) . \PHP_EOL;
    }

    exit;
}

(new Formatter())->format($code, null, null, null, true);

Console::summary('Input formatted by ' . Formatter::class, 'without errors', true);
