<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Generator;
use Lkrms\Facade\File;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Rule\AlignArrowFunctions;
use Lkrms\Pretty\Php\Rule\AlignAssignments;
use Lkrms\Pretty\Php\Rule\AlignChainedCalls;
use Lkrms\Pretty\Php\Rule\AlignComments;
use Lkrms\Pretty\Php\Rule\AlignLists;
use Lkrms\Pretty\Php\Rule\AlignTernaryOperators;
use SplFileInfo;

final class FormatterTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
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
                        $formatter->MirrorBrackets = false;
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
 *   leading asterisk and tab
 *   leading asterisk, space and tab
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
     * Iterate over files in "tests.in" and map them to pathnames in "tests.out"
     *
     * @param string $format The format under test, i.e. one of the keys in
     * {@see FormatterTest::getFileFormats()}'s return value.
     * @return Generator<SplFileInfo,string>
     */
    public static function getFiles(string $format): Generator
    {
        $inDir = dirname(__DIR__) . '.in';
        $outDir = dirname(__DIR__) . '.out';

        $minVersionPatterns = [
            80000 => [
                '#^3rdparty/php-doc/appendices/migration70/new-features/011\.php#',
                '#^3rdparty/php-doc/appendices/migration80/new-features/00[123]\.php#',
                '#^3rdparty/php-doc/appendices/migration81/incompatible/001\.php#',
                '#^3rdparty/php-doc/language/control-structures/match/.*#',
                '#^3rdparty/php-doc/language/exceptions/00[6-7]\.php#',
                '#^3rdparty/php-doc/language/functions/0(05|12|19|20|21|23)\.php#',
                '#^3rdparty/php-doc/language/namespaces/023\.php#',
                '#^3rdparty/php-doc/language/oop5/basic/0(06|16|20)\.php#',
                '#^3rdparty/php-doc/language/oop5/decon/00[1234]\.php#',
                '#^3rdparty/php-doc/language/operators/036\.php#',
                '#^3rdparty/php-doc/language/predefined/attributes/sensitiveparameter/000\.php#',
                '#^3rdparty/php-doc/language/predefined/stringable/000\.php#',
                '#^3rdparty/phpfmt/179-join-to-implode#',
                '#^3rdparty/phpfmt/274-align-comments-in-function#',
                '#^3rdparty/phpfmt/305-lwordwrap-pivot#',
                '#^3rdparty/phpfmt/339-align-objop#',
                '#^3rdparty/phpfmt/341-autosemicolon-objop#',
                '#^attributes-[^/]+\.php#',
            ],
            80100 => [
                '#^3rdparty/php-doc/appendices/migration81/new-features/.*#',
                '#^3rdparty/php-doc/language/((types/)?enumerations|predefined/(backedenum|unitenum))/.*#',
                '#^3rdparty/php-doc/language/(functions/04[345]|namespaces/024)\.php#',
                '#^3rdparty/php-doc/language/oop5/(inheritance/000|properties/00[034567]|traits/012)\.php#',
                '#^3rdparty/php-doc/language/types/declarations/004\.php#',
                '#^3rdparty/php-doc/language/types/integer/000\.php#',
            ],
            80200 => [
                '#^3rdparty/php-doc/language/oop5/basic/00[234]\.php#',
                '#^3rdparty/php-fig/.*#',
            ],
        ];

        $minVersionPatterns = array_reduce(
            array_filter(
                $minVersionPatterns,
                fn(int $key) => PHP_VERSION_ID < $key,
                ARRAY_FILTER_USE_KEY
            ),
            fn(array $carry, array $patterns) =>
                array_merge($carry, $patterns),
            []
        );

        $versionSuffix = PHP_VERSION_ID < 80000
            ? '.PHP74'
            : null;

        // Include:
        // - .php files
        // - files with no extension, and
        // - either of the above with a .fails extension
        $files = File::find($inDir, null, '/(\.php|\/[^.\/]+)(\.fails)?$/');
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $inFile = (string) $file;
            $path = substr($inFile, strlen($inDir));
            $outFile = preg_replace('/\.fails$/', '', $outDir . '/' . $format . $path);

            if ($minVersionPatterns) {
                $path = ltrim($path, '/\\');
                foreach ($minVersionPatterns as $regex) {
                    if (preg_match($regex, $path)) {
                        continue 2;
                    }
                }
            }

            if ($versionSuffix && file_exists(
                $versionOutFile = preg_replace('/(\.php)?$/', $versionSuffix . '\1', $outFile, 1)
            )) {
                $outFile = $versionOutFile;
            }

            yield $file => $outFile;
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
                    AlignAssignments::class,
                    AlignChainedCalls::class,
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
        ];
    }

    /**
     * @return Generator<string,array{string,string,Formatter}>
     */
    public static function filesProvider(): Generator
    {
        $inDir = dirname(__DIR__) . '.in';
        foreach (self::getFileFormats() as $dir => $options) {
            $format = substr($dir, 3);
            $formatter = self::getFormatter($options);
            foreach (self::getFiles($dir) as $file => $outFile) {
                $inFile = (string) $file;
                if ($file->getExtension() === 'fails') {
                    // Don't test if the file is expected to fail
                    continue;
                }
                $path = substr($inFile, strlen($inDir) + 1);
                $code = file_get_contents($inFile);
                $expected = file_get_contents($outFile);
                yield "[{$format}] {$path}" => [$expected, $code, $formatter];
            }
        }
    }
}
