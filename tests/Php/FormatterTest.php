<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Lkrms\Facade\File;
use Lkrms\Pretty\Php\Formatter;
use SplFileInfo;

final class FormatterTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider formatProvider
     *
     * @param array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null} $options
     */
    public function testFormat(string $code, string $expected, array $options = [])
    {
        $formatter = $this->getFormatter($options);
        $first = $formatter->format($code, 3);
        $second = $formatter->format($first, 3, null, true);
        $this->assertSame($expected, $first);
        $this->assertSame($expected, $second, 'Output is not idempotent.');
    }

    public static function formatProvider()
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
use A ?>
PHP,
                <<<'PHP'
<?php
use A
?>
PHP,
            ],
            'PHPDoc comment' => [
                <<<PHP
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
                <<<PHP
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
 *
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
     *
     */
    public function testFiles(string $expected, string $code, Formatter $formatter)
    {
        $first = $formatter->format($code, 3);
        $second = $formatter->format($first, 3, null, true);
        $this->assertSame($expected, $first);
        $this->assertSame($expected, $second, 'Output is not idempotent.');
    }

    public static function filesProvider()
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

        $formatOptions = [
            '01-default' => [
                'insertSpaces' => null,
                'tabSize' => null,
                'skipRules' => [],
                'addRules' => [],
                'skipFilters' => [],
                'callback' => null,
            ],
        ];

        // Include:
        // - .php files
        // - files with no extension, and
        // - either of the above with a .fails extension (these are not tested,
        //   but if their tests.out counterpart doesn't exist, it is generated)
        $files = File::find($inDir, null, '/(\.php|\/[^.\/]+)(\.fails)?$/');
        foreach ($formatOptions as $format => $options) {
            $formatter = self::getFormatter($options);
            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                $inFile = (string) $file;
                $path = substr($inFile, strlen($inDir));
                $outFile = preg_replace('/\.fails$/', '', $outDir . '/' . $format . $path);
                $path = ltrim($path, '/\\');

                if ($minVersionPatterns) {
                    foreach ($minVersionPatterns as $regex) {
                        if (preg_match($regex, $path)) {
                            continue 2;
                        }
                    }
                }

                $code = file_get_contents($inFile);

                if ($versionSuffix && file_exists(
                    $versionOutFile = preg_replace('/(\.php)?$/', $versionSuffix . '\1', $outFile, 1)
                )) {
                    $expected = file_get_contents($versionOutFile);
                } elseif (!file_exists($outFile)) {
                    // Generate a baseline if the output file doesn't exist
                    fprintf(STDERR, "Formatting %s\n", $path);
                    File::maybeCreateDirectory(dirname($outFile));
                    file_put_contents($outFile, $expected = $formatter->format($code, 3));
                } elseif ($file->getExtension() === 'fails') {
                    // Don't test if the file is expected to fail
                    continue;
                } else {
                    $expected = file_get_contents($outFile);
                }

                yield $path => [$expected, $code, $formatter];
            }
        }
    }

    /**
     * @param array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null} $options
     */
    private static function getFormatter(array $options): Formatter
    {
        $formatter = new Formatter(
            $options['insertSpaces'] ?? true,
            $options['tabSize'] ?? 4,
            $options['skipRules'] ?? [],
            $options['addRules'] ?? [],
            $options['skipFilters'] ?? [],
        );
        if ($callback = ($options['callback'] ?? null)) {
            return $callback($formatter);
        }

        return $formatter;
    }
}
