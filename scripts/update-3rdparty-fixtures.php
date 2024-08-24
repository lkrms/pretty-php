#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\PrettyPHP\Tests\FormatterTest;
use Salient\Cli\CliApplication;
use Salient\Core\Facade\Console;
use Salient\Utility\File;
use Salient\Utility\Inflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Salient\Utility\Sys;

require dirname(__DIR__) . '/vendor/autoload.php';

function run(string $command, string ...$arg): string
{
    $command = Sys::escapeCommand([$command, ...$arg]);
    Console::log('Running:', $command);
    $pipe = File::openPipe($command, 'rb');
    $output = File::getContents($pipe);
    $status = File::closePipe($pipe);
    if ($status !== 0) {
        throw new RuntimeException(sprintf('Command exited with status %d: %s', $status, $command));
    }
    return $output;
}

function quote(string $string): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "\'"], $string) . "'";
}

$app = new CliApplication(dirname(__DIR__));

error_reporting(error_reporting() & ~\E_COMPILE_WARNING);

$cacheRoot = $app->getCachePath();
$repoRoot = "$cacheRoot/git";
File::createDir($repoRoot);

$skipUpdate = in_array('--skip-update', $argv);
$fixturesRoot = FormatterTest::getFixturesPath() . '/in/3rdparty';
$rootLength = strlen("$fixturesRoot/");

$repos = [
    'php-doc' => 'https://github.com/php/doc-en.git',
    'per' => 'https://github.com/php-fig/per-coding-style.git',
    'phpfmt' => 'https://github.com/driade/phpfmt8.git',
];

$data = [
    'utf-8.txt' => 'https://www.cl.cam.ac.uk/~mgk25/ucs/examples/UTF-8-test.txt',
    'emoji.txt' => 'https://www.unicode.org/Public/emoji/latest/emoji-test.txt',
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

Console::info('Updating data files');

foreach ($data as $name => $url) {
    $file = "$cacheRoot/$name";
    if (!is_file($file)) {
        Console::log('Retrieving:', $url);
        File::copy($url, $file);
    }
    $data[$name] = $file;
}

$fixtures = 0;
$replaced = 0;

Console::info('Updating php-doc fixtures');

$exclude = preg_quote("$repoRoot/php-doc/reference/", '/');
$files = File::find()
             ->in("$repoRoot/php-doc")
             ->exclude("/^$exclude/")
             ->include('/\.xml$/');

$repoLength = strlen("$repoRoot/php-doc/");
$count = 0;
foreach ($files as $xmlFile) {
    $xml = File::getContents((string) $xmlFile);

    // Remove entities without changing anything between CDATA tags
    /** @var string[] */
    $split = Regex::split('/(<!\[CDATA\[.*?\]\]>)/s', $xml, -1, \PREG_SPLIT_DELIM_CAPTURE);
    if (count($split) < 2) {
        continue;
    }
    $xml = '';
    while ($split) {
        $xml .= Regex::replace(
            '/&[[:alpha:]_][[:alnum:]_.-]*;/', '', array_shift($split)
        );
        if ($split) {
            $xml .= array_shift($split);
        }
    }

    $source = substr((string) $xmlFile, $repoLength, -4);
    $reader = new XMLReader();
    if (!$reader->XML($xml)) {
        throw new UnexpectedValueException(
            sprintf('Unable to process XML in %s', (string) $xmlFile)
        );
    }
    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT
                && $reader->name === 'programlisting'
                && $reader->getAttribute('role') === 'php') {
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
            token_get_all($output, \TOKEN_PARSE);
        } catch (CompileError $ex) {
            $ext = '.invalid';
        }
        $outFile = sprintf('%s/%03d.php%s', $dir, $i, $ext);
        Console::logProgress('Creating', substr($outFile, $rootLength));
        File::writeContents($outFile, $output);
        $replaced++;
    }
}

Console::info('Updating phpfmt fixtures');

$dir = "$fixturesRoot/phpfmt";
Console::log('Updating:', $dir);
File::createDir($dir);
File::pruneDir($dir);

$finders = [
    'original' => [
        File::find()
            ->in("$repoRoot/phpfmt/tests/Original")
            ->include('/\.in$/'),
        '.in',
    ],
    'psr' => [
        File::find()
            ->in("$repoRoot/phpfmt/tests/PSR")
            ->include('/\.in$/'),
        '.in',
    ],
    'unit' => [
        File::find()
            ->in("$repoRoot/phpfmt/tests/Unit/fixtures")
            ->include('/\.txt$/'),
        '.txt',
    ],
];

$files = [];
foreach ($finders as $subdir => [$finder, $suffix]) {
    File::createDir("$dir/$subdir", 0755);
    /** @var SplFileInfo $file */
    foreach ($finder as $file) {
        $files[(string) $file] = "$subdir/" . $file->getBasename($suffix);
    }
}

$count = 0;
foreach ($files as $file => $outFile) {
    $ext = '';
    try {
        // @phpstan-ignore function.resultUnused
        token_get_all(File::getContents($file), \TOKEN_PARSE);
    } catch (CompileError $ex) {
        $ext = '.invalid';
    }
    $outFile = "$dir/" . $outFile . $ext;
    Console::logProgress('Creating', substr($outFile, $rootLength));
    File::copy($file, $outFile);
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

if (!Regex::matchAll(
    "/$markdownRegex/",
    Str::setEol(File::getContents($file)),
    $matches,
    \PREG_UNMATCHED_AS_NULL,
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
    assert($matches[2][$i] !== null);
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
    $heading = trim(Regex::replace(
        '/(?:\.(?![0-9])|[^a-z0-9.])+/i',
        '-',
        Str::lower($heading)
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
            token_get_all($listing, \TOKEN_PARSE);
        } catch (CompileError $ex) {
            $ext = '.invalid';
            Console::warn('Invalid:', $name, null, false);
        }

        $index++;
        $outFile = sprintf('%s/%02d-%s.php%s', $dir, $index, $heading, $ext);
        Console::logProgress('Creating', substr($outFile, $rootLength));
        File::writeContents($outFile, $listing);
        $replaced++;
    }
}

Console::info('Updating UTF-8 test fixture');

$output = <<<'PHP'
<?php
$ascii = "\0\x01\x02\x03\x04\x05\x06\x07\x08\t\n\v\f\r\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\e\x1c\x1d\x1e\x1f";
$blank = "\u{00a0}\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200a}\u{202f}\u{205f}\u{3000}";
$bom = "\u{feff}";
$ignorable = "\u{00ad}\u{202a}\u{202b}\u{202c}\u{202d}\u{202e}\u{2066}\u{2067}\u{2068}\u{2069}";
$visible = "\u{FE19}";

PHP;

Regex::matchAll(
    "/\"[^\"]*(?:[\0-\x08\x0e-\x1f\x7f-\xff][^\"]*)+\"/",
    File::getContents($data['utf-8.txt']),
    $matches,
    \PREG_SET_ORDER,
);

foreach ($matches as $match) {
    $match = Regex::replace('/^"|\s{2,}|\|\s*\n|"$/', '', $match[0]);
    $output .= quote($match) . ';' . \PHP_EOL;
}

$file = $data['emoji.txt'];
$stream = File::open($file, 'r');
$groups = [];
$group = 'ungrouped';
while (true) {
    $line = fgets($stream);
    if ($line === false) {
        if (!feof($stream)) {
            throw new RuntimeException(sprintf('Error reading file: %s', $file));
        }
        break;
    }
    if (Regex::match('/^\h*+#\h*+subgroup:\h*+(?<subgroup>.+)(?<!\h)/', $line, $matches)) {
        $group = $matches['subgroup'];
        continue;
    }
    if (!Regex::match('/^[0-9a-f]+(?: [0-9a-f]+)*/i', $line, $matches)) {
        continue;
    }
    $sequence = '';
    foreach (explode(' ', $matches[0]) as $codepoint) {
        $sequence .= '\u{' . Str::lower($codepoint) . '}';
    }
    $groups[$group][] = $sequence;
}
foreach ($groups as $group => $sequences) {
    $output .= '[' . quote($group) . ' => "' . implode(' ', $sequences) . '"];' . \PHP_EOL;
}

$outFile = $fixturesRoot . '/utf-8.php';
Console::log('Creating', substr($outFile, $rootLength));
File::writeContents($outFile, $output);
$count++;
$fixtures++;
$replaced++;

Console::summary(Inflect::format(
    $fixtures,
    ($replaced !== $fixtures ? 'Updated %d of' : 'Generated') . ' {{#}} {{#:file}}',
    $replaced,
), 'successfully', true);
