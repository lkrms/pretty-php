#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\PrettyPHP\Tests\FormatterTest;
use Salient\Cli\CliApplication;
use Salient\Core\Facade\Console;
use Salient\Utility\File;
use Salient\Utility\Inflect;
use Salient\Utility\Json;
use Salient\Utility\Test;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new CliApplication(dirname(__DIR__));

$versions = $argv[1] ?? null;
if (
    !Test::isInteger($versions)
    || ($versions = (int) $versions) < 1
) {
    throw new RuntimeException('Number of PHP versions in test suite not given');
}

$indexPath = FormatterTest::getIndexFixturePath();
/** @var array<int,string[]> */
$index = file_exists($indexPath)
    ? Json::parseObjectAsArray(File::getContents($indexPath))
    : [];

// Do nothing if a PHP version is missing from the index, otherwise remove any
// files that are invalid on every version of PHP from testing
if (
    count($index) === $versions
    && ($invalid = array_intersect(...$index))
) {
    $dir = FormatterTest::getInputFixturesPath();
    foreach ($invalid as $file) {
        $file = "$dir/$file";
        if (file_exists($file)) {
            Console::logProgress('Suppressing', $file);
            rename($file, $file . '.invalid');
        }
    }

    foreach ($index as $version => $files) {
        $index[$version] = array_values(array_diff($files, $invalid));
    }

    Console::logProgress('Replacing', $indexPath);
    $json = Json::prettyPrint($index);
    File::writeContents($indexPath, $json . \PHP_EOL);

    Console::summary(Inflect::format(
        $invalid,
        '{{#}} {{#:fixture}} removed from testing and fixture index',
    ), 'successfully', true);
} else {
    Console::log('Nothing to do:', $indexPath);
}
