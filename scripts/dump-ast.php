#!/usr/bin/env php
<?php declare(strict_types=1);

use PhpParser\NodeDumper;
use PhpParser\ParserFactory;
use Salient\Cli\CliApplication;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\File;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new CliApplication(dirname(__DIR__));
Console::registerStderrTarget(true);

$code = File::getContents($argv[1] ?? 'php://stdin');

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$ast = $parser->parse($code);
echo (new NodeDumper())->dump($ast) . \PHP_EOL;
