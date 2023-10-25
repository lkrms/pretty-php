#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\Cli\CliApplication;
use Lkrms\Facade\Console;
use Lkrms\Facade\File;
use Lkrms\PrettyPHP\Exception\InvalidSyntaxException;
use Lkrms\PrettyPHP\Tests\FormatterTest;
use Lkrms\Utility\Convert;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new CliApplication(dirname(__DIR__));

if (!ini_get('short_open_tag')) {
    throw new RuntimeException('short_open_tag must be enabled');
}

$pathOffset = strlen(FormatterTest::getInputFixturesPath()) + 1;
$outPathOffset = strlen(dirname(__DIR__)) + 1;
$count = 0;
$replaced = 0;

foreach (FormatterTest::getFileFormats() as $format => $options) {
    $formatter = FormatterTest::getFormatter($options);
    foreach (FormatterTest::getAllFiles($format) as $file => [$outFile, $versionOutFile]) {
        $inFile = (string) $file;
        $path = substr($inFile, $pathOffset);
        $outPath = substr($outFile, $outPathOffset);
        $count++;

        Console::logProgress('Generating', $outPath);

        File::maybeCreateDirectory(dirname($outFile));
        $code = file_get_contents($inFile);
        try {
            $output = $formatter->format($code);
        } catch (InvalidSyntaxException $ex) {
            if (PHP_VERSION_ID >= FormatterTest::TARGET_VERSION_ID) {
                Console::error('Unable to generate:', $outPath);
                throw $ex;
            }
            $invalid[] = $path;
            continue;
        } catch (Throwable $ex) {
            Console::error('Unable to generate:', $outPath);
            throw $ex;
        }
        $message = 'Creating';
        if (file_exists($outFile)) {
            if (file_get_contents($outFile) === $output) {
                continue;
            }
            if ($versionOutFile) {
                $outFile = $versionOutFile;
                $outPath = substr($outFile, $outPathOffset);
                if (file_exists($outFile)) {
                    if (file_get_contents($outFile) === $output) {
                        continue;
                    }
                    $message = 'Replacing';
                }
            } else {
                $message = 'Replacing';
            }
        }
        Console::log($message, $outPath);
        file_put_contents($outFile, $output);
        $replaced++;
    }
}

if ($invalid ?? null) {
    $indexPath = FormatterTest::getMinVersionIndexPath();
    $index = file_exists($indexPath)
        ? json_decode(file_get_contents($indexPath), true)
        : [];

    $version = (PHP_VERSION_ID - PHP_VERSION_ID % 100) + 100;
    if ($version === 70500) {
        $version = 80000;
    }

    foreach ($invalid as $path) {
        foreach ($index as $_version => $_paths) {
            if (($_key = array_search($path, $_paths, true)) !== false) {
                if ($_version >= $version) {
                    continue 2;
                }
                unset($index[$_version][$_key]);
                break;
            }
        }
        $index[$version][] = $path;
    }

    foreach ($index as &$paths) {
        sort($paths);
    }
    ksort($index);

    $json = json_encode($index, JSON_PRETTY_PRINT);
    file_put_contents($indexPath, $json);
}

Console::summary(sprintf(
    $replaced ? 'Updated %1$d of %2$d %3$s' : 'Generated %2$d %3$s',
    $replaced,
    $count,
    Convert::plural($count, 'file')
), 'successfully');
