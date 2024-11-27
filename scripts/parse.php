#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\GenericToken;
use Lkrms\PrettyPHP\Token;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;
use Salient\Cli\CliApplication;
use Salient\Core\Facade\Console;
use Salient\Utility\File;
use Salient\Utility\Get;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * @param GenericToken[]|null $tokens
 */
function dump(?array $tokens): void
{
    if (!$tokens) {
        return;
    }

    foreach ($tokens as $token) {
        printf(
            '%s: %s' . \PHP_EOL,
            $token->getTokenName(),
            Get::code($token->text),
        );
    }
    echo \PHP_EOL;
}

$app = new CliApplication(dirname(__DIR__));
Console::registerStderrTarget();

/** @var bool */
$tokenize = false;
/** @var bool */
$tokenizeForComparison = false;
/** @var bool */
$naive = false;
/** @var bool */
$parseWithPhpParser = false;
/** @var bool */
$dump = false;

$args = array_slice($argv, 1);

foreach ([
    '--tokenize' => &$tokenize,
    '--tokenize-for-comparison' => &$tokenizeForComparison,
    '--naive' => &$naive,
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
    $tokens = Token::$method($code, $naive ? 0 : \TOKEN_PARSE);

    if ($dump) {
        dump($tokens);
    }

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

$formatter = Formatter::build()
                 ->flags($dump ? FormatterFlag::DEBUG : 0)
                 ->build();

$formatter->format($code, null, null, null, true);

if ($dump) {
    dump($formatter->getTokens());
}

Console::summary('Input formatted by ' . Formatter::class, 'without errors', true);
