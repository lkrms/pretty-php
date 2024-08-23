#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\PrettyPHP\Exception\InvalidSyntaxException;
use Lkrms\PrettyPHP\Tests\FormatterTest;
use Salient\Cli\CliApplication;
use Salient\Core\Facade\Console;
use Salient\Utility\File;
use Salient\Utility\Inflect;
use Salient\Utility\Json;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new CliApplication(dirname(__DIR__));

if (!ini_get('short_open_tag')) {
    throw new RuntimeException('short_open_tag must be enabled');
}

$pathOffset = strlen(FormatterTest::getInputFixturesPath()) + 1;
$outPathOffset = strlen(dirname(__DIR__)) + 1;
$count = 0;
$replaced = 0;

foreach (FormatterTest::getFileFormats() as $format => $formatter) {
    foreach (FormatterTest::getAllFiles($format) as $file => [$outFile, $versionOutFile]) {
        $inFile = (string) $file;
        $path = substr($inFile, $pathOffset);
        $outPath = substr($outFile, $outPathOffset);
        $count++;

        Console::logProgress('Generating', $outPath);

        if (isset($invalid[$path])) {
            continue;
        }

        $code = File::getContents($inFile);
        try {
            $output = $formatter->format($code);
        } catch (InvalidSyntaxException $ex) {
            $invalid[$path] = true;
            continue;
        } catch (Throwable $ex) {
            Console::error('Unable to generate:', $outPath);
            throw $ex;
        }
        $message = 'Creating';
        if (file_exists($outFile)) {
            if (File::getContents($outFile) === $output) {
                continue;
            }
            if ($versionOutFile) {
                $outFile = $versionOutFile;
                $outPath = substr($outFile, $outPathOffset);
                if (file_exists($outFile)) {
                    if (File::getContents($outFile) === $output) {
                        continue;
                    }
                    $message = 'Replacing';
                }
            } else {
                $message = 'Replacing';
            }
        } else {
            File::createDir(dirname($outFile));
        }
        Console::log($message, $outPath);
        File::writeContents($outFile, $output);
        $replaced++;
    }
}

if (isset($invalid)) {
    $indexPath = FormatterTest::getIndexFixturePath();
    /** @var array<int,string[]> */
    $index = file_exists($indexPath)
        ? Json::parseObjectAsArray(File::getContents($indexPath))
        : [];

    $invalid = array_keys($invalid);
    sort($invalid);
    $index[\PHP_VERSION_ID - \PHP_VERSION_ID % 100] = $invalid;
    ksort($index);

    $json = Json::prettyPrint($index);
    File::writeContents($indexPath, $json . \PHP_EOL);
}

Console::summary(Inflect::format(
    $count,
    ($replaced ? 'Updated %d of' : 'Generated') . ' {{#}} {{#:file}}',
    $replaced,
), 'successfully', true);
