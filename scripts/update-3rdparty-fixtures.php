#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\Cli\CliApplication;
use Lkrms\Facade\Console;
use Lkrms\Facade\Sys;
use Lkrms\PrettyPHP\Tests\FormatterTest;
use Lkrms\Utility\Convert;
use Lkrms\Utility\File;
use Lkrms\Utility\Pcre;
use Lkrms\Utility\Str;

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
File::createDir($repoRoot);

$skipUpdate = in_array('--skip-update', $argv);
$fixturesRoot = FormatterTest::getFixturesPath() . '/in/3rdparty';

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

$fixtures = 0;
$replaced = 0;

Console::info('Updating php-doc fixtures');

$exclude = preg_quote("$repoRoot/php-doc/reference/", '/');
$files = File::find()
             ->in("$repoRoot/php-doc")
             ->exclude("/^$exclude/")
             ->include('/\.xml$/');

$count = 0;
foreach ($files as $xmlFile) {
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
                    $fixtures++;
                    break;
                }
            }
        }
    }
}

Console::log('Listings extracted from PHP documentation:', (string) $count);

$dir = "$fixturesRoot/php-doc";
File::createDir($dir);
File::pruneDir($dir);

foreach ($listings ?? [] as $source => $sourceListings) {
    $dir = "$fixturesRoot/php-doc/$source";
    Console::log('Updating:', $dir);
    File::createDir($dir);
    foreach ($sourceListings as $i => $output) {
        $ext = '';
        try {
            // @phpstan-ignore-next-line
            token_get_all($output, TOKEN_PARSE);
        } catch (CompileError $ex) {
            $ext = '.invalid';
        }
        $outFile = sprintf('%s/%03d.php%s', $dir, $i, $ext);
        Console::logProgress('Creating', substr($outFile, strlen("$fixturesRoot/")));
        file_put_contents($outFile, $output);
        $replaced++;
    }
}

Console::info('Updating phpfmt fixtures');

$files = File::find()
             ->in("$repoRoot/phpfmt/tests/Original")
             ->include('/\.in$/');

$dir = "$fixturesRoot/phpfmt";
Console::log('Updating:', $dir);
File::createDir($dir);
File::pruneDir($dir);

$count = 0;
foreach ($files as $file) {
    $ext = '';
    try {
        // @phpstan-ignore-next-line
        token_get_all(file_get_contents((string) $file), TOKEN_PARSE);
    } catch (CompileError $ex) {
        $ext = '.invalid';
    }
    $outFile = "$dir/" . $file->getBasename('.in') . $ext;
    Console::logProgress('Creating', substr($outFile, strlen("$fixturesRoot/")));
    copy((string) $file, $outFile);
    $count++;
    $fixtures++;
    $replaced++;
}

Console::log('Listings copied from phpfmt:', (string) $count);

$markdownRegex = <<<'REGEX'
(?xms)
(?<= ^ )
(?:
  (?: \#{1,2} \h+ | \#+ \h+ (?= [0-9] ) ) (?<heading> \V++ ) |
  ```php \n
  (?<code> .*? \n )
  (?= ``` $ )
)
REGEX;

Console::info('Updating PER Coding Style fixtures');
$file = "$repoRoot/per/spec.md";

if (!is_file($file)) {
    throw new RuntimeException(sprintf('File not found: %s', $file));
}

if (!Pcre::matchAll(
    "/$markdownRegex/",
    Str::setEol(file_get_contents($file)),
    $matches,
    PREG_UNMATCHED_AS_NULL,
)) {
    throw new RuntimeException(sprintf('No PHP listings: %s', $file));
}

$count = 0;
$heading = null;
$byHeading = [];
foreach (array_keys($matches[0]) as $i) {
    if ($matches[1][$i] !== null) {
        $heading = $matches[1][$i];
        continue;
    }
    if ($heading === null) {
        Console::warnOnce('PHP listing before heading:', $file, null, false);
        continue;
    }
    $byHeading[$heading][] = $matches[2][$i];
    $count++;
    $fixtures++;
}

Console::log('Listings extracted from PER Coding Style:', (string) $count);

$dir = "$fixturesRoot/php-fig/per";
Console::log('Updating:', $dir);
File::createDir($dir);
File::pruneDir($dir);

$index = 0;
foreach ($byHeading as $heading => $listings) {
    $heading = trim(Pcre::replace(
        '/(?:\.(?![0-9])|[^a-z0-9.])+/i',
        '-',
        strtolower($heading)
    ), '-');

    foreach ($listings as $i => $listing) {
        $name = sprintf('%s-%02d', $heading, $i);

        if (substr(ltrim($listing), 0, 5) !== '<?php') {
            $listing = "<?php\n\n$listing";
            Console::warn('No open tag:', $name, null, false);
        }

        $ext = '';
        try {
            // @phpstan-ignore-next-line
            token_get_all($listing, TOKEN_PARSE);
        } catch (CompileError $ex) {
            $ext = '.invalid';
            Console::warn('Invalid:', $name, null, false);
        }

        $index++;
        $outFile = sprintf('%s/%02d-%s.php%s', $dir, $index, $heading, $ext);
        Console::logProgress('Creating', substr($outFile, strlen("$fixturesRoot/")));
        file_put_contents($outFile, $listing);
        $replaced++;
    }
}

Console::summary(sprintf(
    $replaced !== $fixtures ? 'Updated %1$d of %2$d %3$s' : 'Generated %2$d %3$s',
    $replaced,
    $fixtures,
    Convert::plural($fixtures, 'file')
), 'successfully');
