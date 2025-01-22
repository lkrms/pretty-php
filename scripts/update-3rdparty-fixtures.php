#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\PrettyPHP\Tests\FormatterTest;
use Lkrms\PrettyPHP\Tests\PhpParserTestParser;
use Salient\Cli\CliApplication;
use Salient\Core\Facade\Console;
use Salient\Utility\File;
use Salient\Utility\Inflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Salient\Utility\Sys;

class Updater
{
    private const REPOS = [
        'php-doc' => 'https://github.com/php/doc-en.git',
        'php-parser' => 'https://github.com/nikic/PHP-Parser.git',
        'phpfmt' => 'https://github.com/driade/phpfmt8.git',
        'per' => 'https://github.com/php-fig/per-coding-style.git',
    ];

    private const DATA = [
        'utf-8.txt' => 'https://www.cl.cam.ac.uk/~mgk25/ucs/examples/UTF-8-test.txt',
        'emoji.txt' => 'https://www.unicode.org/Public/emoji/latest/emoji-test.txt',
    ];

    public int $Fixtures;
    public int $Replaced;
    private string $CacheRoot;
    private string $RepoRoot;
    private string $TargetRoot;
    private int $TargetLength;
    private bool $SkipUpdate;
    /** @var array<string,string> */
    private array $Data;

    public function __construct(CliApplication $app)
    {
        $this->CacheRoot = $app->getCachePath();
        $this->RepoRoot = "{$this->CacheRoot}/git";
        $this->TargetRoot = FormatterTest::getFixturesPath() . '/in/3rdparty';
        $this->TargetLength = strlen("{$this->TargetRoot}/");

        /** @var string[] */
        $args = $_SERVER['argv'];
        $this->SkipUpdate = in_array('--skip-update', $args, true);
    }

    public function prepare(): void
    {
        Console::info('Updating source repositories');

        File::createDir($this->RepoRoot);
        foreach (self::REPOS as $dir => $remote) {
            $repo = "{$this->RepoRoot}/$dir";
            if (!is_dir($repo)) {
                $this->run('git', 'clone', $remote, $repo);
                continue;
            }
            if ($this->SkipUpdate) {
                continue;
            }
            $this->run('git', '-C', $repo, 'pull');
        }

        Console::info('Updating data files');

        foreach (self::DATA as $name => $url) {
            $file = "{$this->CacheRoot}/$name";
            if (!is_file($file)) {
                Console::log('Retrieving:', $url);
                File::copy($url, $file);
            }
            $this->Data[$name] = $file;
        }

        $this->Fixtures = 0;
        $this->Replaced = 0;
    }

    public function updatePhpDocFixtures(): void
    {
        Console::info('Updating php-doc fixtures');

        $exclude = preg_quote("{$this->RepoRoot}/php-doc/reference/", '/');
        $files = File::find()
                     ->in("{$this->RepoRoot}/php-doc")
                     ->exclude("/^$exclude/")
                     ->include('/\.xml$/');

        $repoLength = strlen("{$this->RepoRoot}/php-doc/");
        $listings = [];
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
                $xml .= Regex::replace('/&[[:alpha:]_][[:alnum:]_.-]*;/', '', array_shift($split));
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
                if (
                    $reader->nodeType === XMLReader::ELEMENT
                    && $reader->name === 'programlisting'
                    && $reader->getAttribute('role') === 'php'
                ) {
                    while ($reader->read()) {
                        if ($reader->nodeType === XMLReader::CDATA) {
                            $listings[$source][] = trim($reader->value);
                            $count++;
                            $this->Fixtures++;
                            break;
                        }
                    }
                }
            }
        }

        $dir = "{$this->TargetRoot}/php-doc";
        Console::log('Populating:', $dir);
        File::createDir($dir);
        File::pruneDir($dir);

        foreach ($listings as $source => $sourceListings) {
            $dir = "{$this->TargetRoot}/php-doc/$source";
            File::createDir($dir);
            foreach ($sourceListings as $i => $output) {
                $outFile = sprintf('%s/%03d.php', $dir, $i);
                Console::logProgress('Creating', substr($outFile, $this->TargetLength));
                File::writeContents($outFile, Str::setEol($output, \PHP_EOL));
                $this->Replaced++;
            }
        }

        Console::log('Listings extracted from PHP documentation:', (string) $count);
    }

    public function updatePhpParserFixtures(): void
    {
        Console::info('Updating php-parser fixtures');

        $dir = "{$this->RepoRoot}/php-parser";
        $exclude = preg_quote("$dir/test/code/parser/errorHandling/", '/');
        $files = File::find()
                     ->in("$dir/test/code/parser", "$dir/test/code/prettyPrinter")
                     ->exclude("/^$exclude/")
                     ->include('/\.test$/');
        $parser = new PhpParserTestParser();

        $repoLength = strlen("$dir/test/code/");
        $listings = [];
        $count = 0;
        foreach ($files as $file) {
            $code = File::getContents((string) $file);

            [, $tests] = $parser->parseTest($code, 2);

            $source = substr((string) $file, $repoLength, -5);
            $listings[$source] = $tests;
            $tests = count($tests);
            $count += $tests;
            $this->Fixtures += $tests;
        }

        $dir = "{$this->TargetRoot}/php-parser";
        Console::log('Populating:', $dir);
        File::createDir($dir);
        File::pruneDir($dir);

        foreach ($listings as $source => $sourceListings) {
            $dir = "{$this->TargetRoot}/php-parser/$source";
            File::createDir($dir);
            foreach ($sourceListings as $i => $output) {
                $outFile = sprintf('%s/%03d.php', $dir, $i);
                Console::logProgress('Creating', substr($outFile, $this->TargetLength));
                File::writeContents($outFile, Str::setEol($output, \PHP_EOL));
                $this->Replaced++;
            }
        }

        Console::log('Listings extracted from PHP Parser tests:', (string) $count);
    }

    public function updatePhpfmtFixtures(): void
    {
        Console::info('Updating phpfmt fixtures');

        $dir = "{$this->TargetRoot}/phpfmt";
        Console::log('Populating:', $dir);
        File::createDir($dir);
        File::pruneDir($dir);

        $finders = [
            'original' => [
                File::find()
                    ->in("{$this->RepoRoot}/phpfmt/tests/Original")
                    ->include('/\.in$/'),
                '.in',
            ],
            'psr' => [
                File::find()
                    ->in("{$this->RepoRoot}/phpfmt/tests/PSR")
                    ->include('/\.in$/'),
                '.in',
            ],
            'unit' => [
                File::find()
                    ->in("{$this->RepoRoot}/phpfmt/tests/Unit/fixtures")
                    ->include('/\.txt$/'),
                '.txt',
            ],
        ];

        $files = [];
        foreach ($finders as $subdir => [$finder, $suffix]) {
            File::createDir("$dir/$subdir");
            /** @var SplFileInfo $file */
            foreach ($finder as $file) {
                $files[(string) $file] = "$subdir/" . $file->getBasename($suffix);
            }
        }

        $count = 0;
        foreach ($files as $file => $outFile) {
            $code = File::getContents((string) $file);
            $outFile = "$dir/" . $outFile;
            Console::logProgress('Creating', substr($outFile, $this->TargetLength));
            File::writeContents($outFile, Str::setEol($code, \PHP_EOL));
            $count++;
            $this->Fixtures++;
            $this->Replaced++;
        }

        Console::log('Listings copied from phpfmt:', (string) $count);
    }

    public function updatePhpFigFixtures(): void
    {
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

        Console::info('Updating php-fig fixtures');
        $file = "{$this->RepoRoot}/per/spec.md";

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
            $this->Fixtures++;
        }

        $dir = "{$this->TargetRoot}/php-fig/per";
        Console::log('Populating:', $dir);
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

                if (!Str::startsWith(ltrim($listing), '<?php')) {
                    $listing = "<?php\n\n$listing";
                    Console::warn('No open tag:', $name, null, false);
                }

                try {
                    // @phpstan-ignore function.resultUnused
                    token_get_all($listing, \TOKEN_PARSE);
                } catch (CompileError $ex) {
                    Console::warn('Invalid:', $name, null, false);
                }

                $index++;
                $outFile = sprintf('%s/%02d-%s.php', $dir, $index, $heading);
                Console::logProgress('Creating', substr($outFile, $this->TargetLength));
                File::writeContents($outFile, Str::setEol($listing, \PHP_EOL));
                $this->Replaced++;
            }
        }

        Console::log('Listings extracted from PER Coding Style:', (string) $count);
    }

    public function updateUtf8Fixture(): void
    {
        Console::info('Updating utf-8 fixture');

        $output = implode(\PHP_EOL, [
            '<?php',
            '$ascii = "\0\x01\x02\x03\x04\x05\x06\x07\x08\t\n\v\f\r\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\e\x1c\x1d\x1e\x1f";',
            '$blank = "\u{00a0}\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200a}\u{202f}\u{205f}\u{3000}";',
            '$bom = "\u{feff}";',
            '$ignorable = "\u{00ad}\u{202a}\u{202b}\u{202c}\u{202d}\u{202e}\u{2066}\u{2067}\u{2068}\u{2069}";',
            '$visible = "\u{FE19}";',
        ]) . \PHP_EOL;

        Regex::matchAll(
            "/\"[^\"]*(?:[\0-\x08\x0e-\x1f\x7f-\xff][^\"]*)+\"/",
            File::getContents($this->Data['utf-8.txt']),
            $matches,
            \PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $match = Regex::replace('/^"|\s{2,}|\|\s*\n|"$/', '', $match[0]);
            $output .= $this->quote($match) . ';' . \PHP_EOL;
        }

        $file = $this->Data['emoji.txt'];
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
            $output .= '[' . $this->quote($group) . ' => "' . implode(' ', $sequences) . '"];' . \PHP_EOL;
        }

        $outFile = $this->TargetRoot . '/utf-8.php';
        Console::log('Creating', substr($outFile, $this->TargetLength));
        File::writeContents($outFile, $output);
        $this->Fixtures++;
        $this->Replaced++;
    }

    private function run(string $command, string ...$arg): string
    {
        $command = Sys::escapeCommand([$command, ...$arg]);
        Console::log('Running:', $command);
        $pipe = File::openPipe($command, 'rb');
        $output = File::getContents($pipe);
        $status = File::closePipe($pipe);
        if ($status !== 0) {
            throw new RuntimeException(sprintf(
                'Command exited with status %d: %s',
                $status,
                $command,
            ));
        }
        return $output;
    }

    private function quote(string $string): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\'"], $string) . "'";
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

umask(022);

$app = new CliApplication(dirname(__DIR__));

error_reporting(error_reporting() & ~\E_COMPILE_WARNING);
if (\PHP_VERSION_ID < 80000) {
    error_reporting(error_reporting() & ~\E_DEPRECATED);
}

$updater = new Updater($app);

$updater->prepare();

$updater->updatePhpDocFixtures();
$updater->updatePhpParserFixtures();
$updater->updatePhpfmtFixtures();
$updater->updatePhpFigFixtures();
$updater->updateUtf8Fixture();

Console::summary(Inflect::format(
    $updater->Fixtures,
    ($updater->Replaced !== $updater->Fixtures ? 'Updated %d of' : 'Generated') . ' {{#}} {{#:file}}',
    $updater->Replaced,
), 'successfully', true);
