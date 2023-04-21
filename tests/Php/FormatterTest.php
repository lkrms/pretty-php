<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Lkrms\Facade\File;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Rule\AlignComments;
use Lkrms\Pretty\Php\Rule\ApplyMagicComma;
use Lkrms\Pretty\Php\Rule\PreserveOneLineStatements;
use Lkrms\Pretty\Php\Rule\ReindentHeredocs;
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
    $b
];

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
                ['addRules' => [ReindentHeredocs::class]]
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
        $pathOptions = [
            '#^phpfmt/.*#' => [
                'addRules' => [
                    AlignComments::class,
                    PreserveOneLineStatements::class,
                    ReindentHeredocs::class,
                ],
                'skipRules' => [
                    ApplyMagicComma::class,
                ],
                'insertSpaces' => false,
            ],
            '#^php-doc/.*#' => [
                'addRules' => [
                    AlignComments::class,
                    PreserveOneLineStatements::class,
                    ReindentHeredocs::class,
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
            $path = ltrim($path, DIRECTORY_SEPARATOR);

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
