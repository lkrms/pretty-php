#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\Cli\CliApplication;
use Lkrms\Facade\Console;
use Lkrms\Facade\File;
use Lkrms\Facade\Sys;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Pcre;

require dirname(__DIR__) . '/vendor/autoload.php';

function run(string $command, string ...$arg): string
{
    $command = Sys::escapeCommand([$command, ...$arg]);
    Console::log('Running:', $command);
    $handle = popen($command, 'rb');
    $output = stream_get_contents($handle);
    $status = pclose($handle);
    if ($status !== 0) {
        throw new RuntimeException(sprintf('Command exited with status %d: %s', $status, $command));
    }
    return $output;
}

$app = new CliApplication(dirname(__DIR__));

error_reporting(error_reporting() & ~E_COMPILE_WARNING);

$repoRoot = $app->getCachePath() . '/git';
File::maybeCreateDirectory($repoRoot);

$skipUpdate = in_array('--skip-update', $argv);
$fixturesRoot = dirname(__DIR__) . '/tests/fixtures/in/3rdparty';

$repos = [
    'php-doc' => 'https://github.com/php/doc-en.git',
    'per' => 'https://github.com/php-fig/per-coding-style.git',
    'phpfmt' => 'https://github.com/driade/phpfmt8.git',
];

Console::info('Updating source repositories');
foreach ($repos as $dir => $remote) {
    $repo = "$repoRoot/$dir";
    if (!is_dir($repo)) {
        run('git', 'clone', $remote, $repo);
        continue;
    }
    if ($skipUpdate) {
        continue;
    }
    run('git', '-C', $repo, 'pull');
}

Console::info('Updating php-doc fixtures');
$exclude = preg_quote("$repoRoot/php-doc/reference/", '/');
$count = 0;
$replaced = 0;
foreach (File::find(
    "$repoRoot/php-doc",
    "/^$exclude/",
    '/\.xml$/'
) as $xmlFile) {
    $xml = file_get_contents((string) $xmlFile);

    // Remove entities without changing anything between CDATA tags
    /** @var string[] */
    $split = Pcre::split('/(<!\[CDATA\[.*?\]\]>)/s', $xml, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($split) < 2) {
        continue;
    }
    $xml = '';
    while ($split) {
        $xml .= Pcre::replace(
            '/&[[:alpha:]_][[:alnum:]_.-]*;/', '', array_shift($split)
        );
        if ($split) {
            $xml .= array_shift($split);
        }
    }

    $source = substr((string) $xmlFile, strlen("$repoRoot/php-doc/"), -4);
    $reader = XMLReader::XML($xml);
    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT &&
                $reader->name === 'programlisting' &&
                $reader->getAttribute('role') === 'php') {
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::CDATA) {
                    $listings[$source][] = trim($reader->value);
                    $count++;
                    break;
                }
            }
        }
    }
}

Console::log('Listings extracted from PHP documentation:', (string) $count);

foreach ($listings ?? [] as $source => $sourceListings) {
    $dir = "$fixturesRoot/php-doc/$source";
    File::maybeCreateDirectory($dir);
    foreach ($sourceListings as $i => $output) {
        $ext = '';
        try {
            // @phpstan-ignore-next-line
            token_get_all($output, TOKEN_PARSE);
        } catch (ParseError $ex) {
            $ext = '.invalid';
        }
        $outFile = sprintf('%s/%03d.php%s', $dir, $i, $ext);
        $message = 'Creating';
        if (file_exists($outFile)) {
            if (file_get_contents($outFile) === $output) {
                continue;
            }
            $message = 'Replacing';
        }
        Console::log($message, substr($outFile, strlen("$fixturesRoot/")));
        file_put_contents($outFile, $output);
        $replaced++;
    }
}

Console::summary(sprintf(
    $replaced ? 'Updated %1$d of %2$d %3$s' : 'Generated %2$d %3$s',
    $replaced,
    $count,
    Convert::plural($count, 'file')
), 'successfully');
