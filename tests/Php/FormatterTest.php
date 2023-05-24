<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Lkrms\Facade\File;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Rule\AlignComments;
use Lkrms\Pretty\Php\Rule\ApplyMagicComma;
use Lkrms\Pretty\Php\Rule\PreserveOneLineStatements;
use Lkrms\Pretty\Php\Rule\SimplifyStrings;
use Lkrms\Pretty\Php\Rule\SpaceDeclarations;
use SplFileInfo;

final class FormatterTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider formatProvider
     *
     * @param array{insertSpaces?:bool,tabSize?:int,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)} $options
     */
    public function testFormat(string $code, string $expected, array $options = [])
    {
        $formatter = $this->getFormatter($options);
        $this->assertSame($expected, $formatter->format($code, 3));
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
     * @param array{insertSpaces?:bool,tabSize?:int,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)} $options
     */
    public function testFiles(string $code, string $expected, array $options)
    {
        $formatter = $this->getFormatter($options);
        $this->assertSame($expected, $formatter->format($code, 3, null, true));
    }

    public static function filesProvider()
    {
        $inDir = dirname(__DIR__) . '.in';
        $outDir = dirname(__DIR__) . '.out';

        $minVersionPatterns = [
            80000 => [
                // - Arbitrary `new` and `instanceof` expressions
                // - Constructor property promotion
                // - `match`
                // - Named arguments
                // - Optional variable in `catch`
                // - Nullsafe operator (`?->`),
                // - Reserved keywords in namespaces
                // - `static` return type
                // - `Stringable`
                // - `throw` in expressions
                // - Trailing commas in argument lists
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
                '#^3rdparty/php-doc/language/predefined/stringable/000\.php#',
                '#^3rdparty/phpfmt/179-join-to-implode#',
                '#^3rdparty/phpfmt/274-align-comments-in-function#',
                '#^3rdparty/phpfmt/305-lwordwrap-pivot#',
                '#^3rdparty/phpfmt/339-align-objop#',
                '#^3rdparty/phpfmt/341-autosemicolon-objop#',
            ],
            80100 => [
                '#^3rdparty/php-doc/appendices/migration81/new-features/.*#',
                // Intersection types
                '#^3rdparty/php-doc/language/types/declarations/004\.php#',
                // Enumerations
                '#^3rdparty/php-doc/language/((types/)?enumerations|predefined/(backedenum|unitenum))/.*#',
                // First class callable syntax
                '#^3rdparty/php-doc/language/(functions/04[345]|namespaces/024)\.php#',
                // `readonly` properties
                '#^3rdparty/php-doc/language/oop5/(inheritance/000|properties/00[034567]|traits/012)\.php#',
                // Octal integer prefix
                '#^3rdparty/php-doc/language/types/integer/000\.php#',
            ],
            80200 => [
                // `readonly` classes
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

        $pathOptions = [
            '#^3rdparty/phpfmt/.*#' => [
                'addRules' => [
                    AlignComments::class,
                    PreserveOneLineStatements::class,
                ],
                'skipRules' => [
                    ApplyMagicComma::class,
                ],
                'insertSpaces' => false,
            ],
            '#^3rdparty/php-doc/.*#' => [
                'addRules' => [
                    AlignComments::class,
                    PreserveOneLineStatements::class,
                ],
                'skipRules' => [
                    SimplifyStrings::class,
                    SpaceDeclarations::class,
                ],
            ],
        ];

        // Include:
        // - .php files
        // - files with no extension, and
        // - either of the above with a .fails extension (these are not tested,
        //   but if their tests.out counterpart doesn't exist, it is generated)
        $files = File::find($inDir, null, '/(\.php|\/[^.\/]+)(\.fails)?$/');
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $inFile = (string) $file;
            $path = substr($inFile, strlen($inDir));
            $outFile = preg_replace('/\.fails$/', '', $outDir . $path);
            $path = ltrim($path, '/\\');

            if ($minVersionPatterns) {
                foreach ($minVersionPatterns as $regex) {
                    if (preg_match($regex, $path)) {
                        continue 2;
                    }
                }
            }

            $fileOptions = [];
            foreach ($pathOptions as $regex => $options) {
                if (preg_match($regex, $path)) {
                    $fileOptions = $options;
                    break;
                }
            }

            $input = file_get_contents($inFile);

            // Generate a baseline if the output file doesn't exist
            if (!file_exists($outFile)) {
                fprintf(STDERR, "Formatting %s\n", $path);
                File::maybeCreateDirectory(dirname($outFile));
                $formatter = self::getFormatter($fileOptions);
                file_put_contents($outFile, $output = $formatter->format($input, 3, null, true));
            } elseif ($file->getExtension() === 'fails') {
                // Don't test if the file is expected to fail
                continue;
            } else {
                $output = file_get_contents($outFile);
            }

            yield $path => [$input, $output, $fileOptions];
        }
    }

    /**
     * @param array{insertSpaces?:bool,tabSize?:int,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)} $options
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
