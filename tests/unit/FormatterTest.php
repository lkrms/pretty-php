<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Generator;
use Lkrms\Facade\File;
use Lkrms\Facade\Sys;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Rule\AlignArrowFunctions;
use Lkrms\Pretty\Php\Rule\AlignChains;
use Lkrms\Pretty\Php\Rule\AlignComments;
use Lkrms\Pretty\Php\Rule\AlignData;
use Lkrms\Pretty\Php\Rule\AlignLists;
use Lkrms\Pretty\Php\Rule\AlignTernaryOperators;
use Lkrms\Pretty\Php\Rule\StrictLists;
use SplFileInfo;

final class FormatterTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    public const TARGET_VERSION_ID = 80200;

    /**
     * @dataProvider formatProvider
     *
     * @param array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null} $options
     */
    public function testFormat(string $expected, string $code, array $options = []): void
    {
        $this->assertFormatterOutputIs($expected, $code, $this->getFormatter($options));
    }

    /**
     * @return array<string,array{string,string,array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null}}>
     */
    public static function formatProvider(): array
    {
        return [
            'empty string' => [
                '',
                '',
            ],
            'no symmetrical bracket' => [
                <<<'PHP'
<?php
[$a,
    $b
];
[
    $a,
    $b];

PHP,
                <<<'PHP'
<?php
[$a,
$b
];
[
$a,
$b];
PHP,
                ['callback' =>
                    function (Formatter $formatter): Formatter {
                        $formatter->SymmetricalBrackets = false;
                        return $formatter;
                    }],
            ],
            'empty heredoc' => [
                <<<'PHP'
<?php
$a = <<<EOF
    EOF;

PHP,
                <<<'PHP'
<?php
$a = <<<EOF
EOF;
PHP,
            ],
            'import with close tag terminator' => [
                <<<'PHP'
<?php
use A
?>
PHP,
                <<<'PHP'
<?php
use A ?>
PHP,
            ],
            'PHPDoc comment' => [
                <<<'PHP'
<?php
/**
 * leading asterisk and space
 * leading asterisk
 * 	leading asterisk and tab
 * 	leading asterisk, space and tab
 *
 *
 * no leading asterisk
 * leading tab and no leading asterisk
 */

PHP,
                <<<'PHP'
<?php
/**
* leading asterisk and space
*leading asterisk
*	leading asterisk and tab
* 	leading asterisk, space and tab
* 
*
no leading asterisk
	leading tab and no leading asterisk

  */
PHP,
            ],
            'alternative syntax #1' => [
                <<<'PHP'
<?php
if ($a):
    b();
    while ($c):
        d();
    endwhile;
else:
    e();
endif;
f();

PHP,
                <<<'PHP'
<?php
if ($a):
b();
while ($c):
d();
endwhile;
else:
e();
endif;
f();
PHP,
            ],
            'alternative syntax #2' => [
                <<<'PHP'
<?php
if ($a):
    while ($b):
    endwhile;
else:
endif;

PHP,
                <<<'PHP'
<?php
if ($a):
while ($b):
endwhile;
else:
endif;
PHP,
            ],
        ];
    }

    /**
     * @dataProvider filesProvider
     */
    public function testFiles(string $expected, string $code, Formatter $formatter): void
    {
        $this->assertFormatterOutputIs($expected, $code, $formatter);
    }

    /**
     * Iterate over files in 'tests/fixtures/in' and map them to pathnames in
     * 'tests/fixtures/out.<format>'
     *
     * @param string $format The format under test, i.e. one of the keys in
     * {@see FormatterTest::getFileFormats()}'s return value.
     * @return Generator<SplFileInfo,string>
     */
    public static function getFiles(string $format): Generator
    {
        return self::doGetFiles($format);
    }

    /**
     * Iterate over files in 'tests/fixtures/in' and map them to pathnames in
     * 'tests/fixtures/out.<format>' without adjusting for the PHP version
     *
     * @return Generator<SplFileInfo,array{string,string|null}>
     */
    public static function getAllFiles(string $format): Generator
    {
        return self::doGetFiles($format, true);
    }

    /**
     * @phpstan-return (
     *     $all is false
     *     ? Generator<SplFileInfo,string>
     *     : Generator<SplFileInfo,array{string,string|null}>
     * )
     */
    private static function doGetFiles(string $format, bool $all = false): Generator
    {
        $inDir = self::getInputFixturesPath();
        $outDir = self::getOutputFixturesPath($format);
        $pathOffset = strlen($inDir) + 1;

        $index = [];
        if (!$all && is_file($indexPath = self::getMinVersionIndexPath())) {
            $index = array_merge(...array_filter(
                json_decode(file_get_contents($indexPath), true),
                fn(int $key) =>
                    PHP_VERSION_ID < $key,
                ARRAY_FILTER_USE_KEY
            ));
            $index = array_combine(
                $index,
                array_fill(0, count($index), true)
            );
        }

        $versionSuffix =
            PHP_VERSION_ID < 80000
                ? '.PHP74'
                : (PHP_VERSION_ID < 80100
                    ? '.PHP80'
                    : (PHP_VERSION_ID < 80200
                        ? '.PHP81'
                        : null));

        // Include:
        // - .php files
        // - files with no extension, and
        // - either of the above with a .fails extension
        $files = File::find(
            $format === '04-psr12' ? $inDir . '/3rdparty/php-fig' : $inDir,
            null,
            '/(\.php|\/[^.\/]+)(\.fails)?$/'
        );
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $inFile = (string) $file;
            $path = substr($inFile, $pathOffset);

            if ($index[$path] ?? false) {
                continue;
            }

            $outFile = preg_replace('/\.fails$/', '', "$outDir/$path");
            if ($versionSuffix) {
                $versionOutFile = preg_replace('/(?<!\G)(\.php)?$/', "$versionSuffix\$1", $outFile);
                if (!$all && file_exists($versionOutFile)) {
                    $outFile = $versionOutFile;
                }
            }

            yield $file => $all
                ? [$outFile, $versionOutFile ?? null]
                : $outFile;
        }
    }

    /**
     * Get the formats applied to files in "tests.in" during testing
     *
     * @return array<string,array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null}>
     */
    public static function getFileFormats(): array
    {
        return [
            '01-default' => [
                'insertSpaces' => null,
                'tabSize' => null,
                'skipRules' => [],
                'addRules' => [],
                'skipFilters' => [],
                'callback' => null,
            ],
            '02-aligned' => [
                'insertSpaces' => null,
                'tabSize' => null,
                'skipRules' => [],
                'addRules' => [
                    AlignData::class,
                    AlignChains::class,
                    AlignComments::class,
                    AlignArrowFunctions::class,
                    AlignLists::class,
                    AlignTernaryOperators::class,
                ],
                'skipFilters' => [],
                'callback' => null,
            ],
            '03-tab' => [
                'insertSpaces' => false,
                'tabSize' => 8,
                'skipRules' => [],
                'addRules' => [],
                'skipFilters' => [],
                'callback' => null,
            ],
            '04-psr12' => [
                'insertSpaces' => true,
                'tabSize' => 4,
                'skipRules' => [],
                'addRules' => [
                    StrictLists::class,
                ],
                'skipFilters' => [],
                'callback' =>
                    fn(Formatter $f) =>
                        $f->withPsr12Compliance(),
            ],
        ];
    }

    /**
     * @return Generator<string,array{string,string,Formatter}>
     */
    public static function filesProvider(): Generator
    {
        $pathOffset = strlen(self::getInputFixturesPath()) + 1;
        foreach (self::getFileFormats() as $dir => $options) {
            $format = substr($dir, 3);
            $formatter = self::getFormatter($options);
            foreach (self::getFiles($dir) as $file => $outFile) {
                $inFile = (string) $file;
                if ($file->getExtension() === 'fails') {
                    // Don't test if the file is expected to fail
                    continue;
                }
                $path = substr($inFile, $pathOffset);
                $code = file_get_contents($inFile);
                $expected = file_get_contents($outFile);
                yield "[{$format}] {$path}" => [$expected, $code, $formatter];
            }
        }
    }

    public static function getMinVersionIndexPath(): string
    {
        return self::getInputFixturesPath() . '/versions.json';
    }

    public static function getInputFixturesPath(): string
    {
        return self::getFixturesPath() . '/in';
    }

    public static function getOutputFixturesPath(string $format): string
    {
        return self::getFixturesPath() . "/out.{$format}";
    }

    public static function getFixturesPath(): string
    {
        return dirname(__DIR__) . '/fixtures';
    }

    protected function setUp(): void
    {
        Sys::pushTimers();
    }

    protected function tearDown(): void
    {
        Sys::popTimers();
    }
}