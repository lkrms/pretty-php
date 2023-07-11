#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\Cli\CliApplication;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\File;
use Lkrms\Pretty\Tests\Php\FormatterTest;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new CliApplication(dirname(__DIR__));

$count = 0;
$replaced = 0;

foreach (FormatterTest::getFileFormats() as $dir => $options) {
    $formatter = FormatterTest::getFormatter($options);
    foreach (FormatterTest::getFiles($dir) as $file => $outFile) {
        $inFile = (string) $file;
        $path = substr($outFile, strlen(dirname(__DIR__)) + 1);

        Console::logProgress('Generating', $path);

        File::maybeCreateDirectory(dirname($outFile));
        $code = file_get_contents($inFile);
        try {
            $output = $formatter->format($code);
        } catch (Throwable $ex) {
            Console::error('Unable to generate:', $path);
            throw $ex;
        }
        if (file_get_contents($outFile) !== $output) {
            Console::log('Replacing', $path);
            file_put_contents($outFile, $output);
            $replaced++;
        }
        $count++;
    }
}

Console::summary(sprintf(
    $replaced ? 'Replaced %1$d of %2$d %3$s' : 'Generated %2$d %3$s',
    $replaced,
    $count,
    Convert::plural($count, 'file')
), 'successfully');
