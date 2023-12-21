<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Contract\Extension;
use Lkrms\PrettyPHP\Rule\AlignArrowFunctions;
use Lkrms\PrettyPHP\Rule\AlignChains;
use Lkrms\PrettyPHP\Rule\AlignComments;
use Lkrms\PrettyPHP\Rule\AlignData;
use Lkrms\PrettyPHP\Rule\AlignLists;
use Lkrms\PrettyPHP\Rule\AlignTernaryOperators;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;
use Lkrms\Utility\Arr;
use Lkrms\Utility\File;
use Lkrms\Utility\Json;
use Lkrms\Utility\Pcre;
use Generator;
use SplFileInfo;

final class FormatterTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    public const TARGET_VERSION_ID = 80300;

    /**
     * @dataProvider formatProvider
     *
     * @param Formatter|FormatterB $formatter
     */
    public function testFormat(string $expected, string $code, $formatter): void
    {
        $this->assertFormatterOutputIs($expected, $code, $formatter);
    }

    /**
     * @return array<string,array{string,string,Formatter|FormatterB}>
     */
    public static function formatProvider(): array
    {
        $formatterB = Formatter::build();
        $formatter = $formatterB->go();

        return [
            'empty string' => [
                '',
                '',
                $formatter,
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
                $formatter,
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
                $formatter,
            ],
            'PHPDoc comment #1' => [
                <<<'PHP'
<?php

/**
 * leading asterisk and space
 * leading asterisk
 * 	leading asterisk and tab
 * 	leading asterisk, space and tab
 * trailing space:
 *
 *
 * no leading asterisk
 *
 * 	leading tab and no leading asterisk
 */

PHP,
                <<<'PHP'
<?php
/**
* leading asterisk and space
*leading asterisk
*	leading asterisk and tab
* 	leading asterisk, space and tab
* trailing space:
* 
*
no leading asterisk

	leading tab and no leading asterisk

  */
PHP,
                $formatter,
            ],
            'PHPDoc comment #2' => [
                <<<'PHP'
<?php

/** comment */

/** comment */

/** comment */

/** comment */

/** comment */

/** comment */

/** comment */

/** comment */

/** comment */

/** @return foo::* */

/**
 * @return foo::*
 */
function foo()
{
    return foo::BAR;
}

/**
 * * <== look, it's an asterisk in a summary
 *
 * * <== and another in a description
 *
 * (There's one at the end, too.)
 *
 * *
 */

PHP,
                <<<'PHP'
<?php

/**
 *
 * comment
 */

/**
 * comment
 *
 */

/**
 *
 * comment
 *
 */

/** comment
 */

/**
 * comment */

/**
comment */

/**
 comment */

/**
 * comment **/

/** comment */

/**
 *
 * @return foo::*
 */

/** @return foo::* */
function foo()
{
    return foo::BAR;
}

/**
 * * <== look, it's an asterisk in a summary
 *
 * * <== and another in a description
 *
 * (There's one at the end, too.)

 * *
 */

/**
 *
 */

/** */
PHP,
                $formatter,
            ],
            'C comment' => [
                <<<'PHP'
<?php

/*
 * comment
 */

/*
 * comment
 */

/*
 * comment
 */

/*
 * comment
 */

/*
 * comment
 */

/*
 * comment
 */

/*
 * comment
 */

/*
 * comment
 */

/* comment */

/*
 * @return foo::*
 */

/*
 * @return foo::*
 */
function foo()
{
    return foo::BAR;
}

/*
 * * <== look, it's an asterisk in a comment
 *
 * * <== and another
 *
 * (There's one at the end, too.)
 *
 * *
 */

/*
 *
 */

/* */

PHP,
                <<<'PHP'
<?php

/*
 *
 * comment
 */

/*
 * comment
 *
 */

/*
 *
 * comment
 *
 */

/* comment
 */

/*
 * comment */

/*
comment */

/*
 comment */

/*
 * comment **/

/* comment */

/*
 *
 * @return foo::*
 */

/*
 *
 * @return foo::*
 */
function foo()
{
    return foo::BAR;
}

/*
 * * <== look, it's an asterisk in a comment
 *
 * * <== and another
 *
 * (There's one at the end, too.)

 * *
 */

/*
 *
 */

/* */
PHP,
                $formatter,
            ],
            'one-line comments' => [
                <<<'PHP'
<?php
/* comment */
/** docblock */
/* comment */
/* comment */
/* comment */
/* comment */

/* */
/******/

PHP,
                <<<'PHP'
<?php
/*  comment  */
/**  docblock  **/
/***  comment  ***/
/*comment*/
/**comment**/
/***comment***/
/**   **/
/***   ***/
/******/
PHP,
                $formatter,
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
                $formatter,
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
                $formatter,
            ],
            'empty statements inside braces' => [
                <<<'PHP'
<?php
function a()
{
    ;
    if ($b) {
        ;
        c();
        if ($d) {
            e();
        }
    }
    f();
    g();
}

PHP,
                <<<'PHP'
<?php
function a()
{;
if ($b) {;
    c();
if ($d) {
    e();
} }
    f();
    g(); }
PHP,
                $formatter,
            ],
            'ternary with closure return type in expression 1' => [
                <<<'PHP'
<?php
$filter =
    $exclude
        ? function ($value, $key, $iterator) use ($exclude): bool {
            return (bool) preg_match($exclude, $key);
        }
        : null;

PHP,
                <<<'PHP'
<?php
$filter =
$exclude
? function ($value, $key, $iterator) use ($exclude): bool {
return (bool) preg_match($exclude, $key);
}
: null;
PHP,
                $formatter,
            ],
            'label after close brace' => [
                <<<'PHP'
<?php
if ($foo) {
    goto bar;
}
bar:
qux();

PHP,
                <<<'PHP'
<?php
if ($foo) {
goto bar;
}
bar: qux();
PHP,
                $formatter,
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
     * @return Generator<string,array{string,string,Formatter}>
     */
    public static function filesProvider(): Generator
    {
        $pathOffset = strlen(self::getInputFixturesPath()) + 1;
        foreach (self::getFileFormats() as $dir => $formatter) {
            $format = substr($dir, 3);
            foreach (self::getFiles($dir) as $file => $outFile) {
                $inFile = (string) $file;

                // Don't test if the file is expected to fail
                if ($file->getExtension() === 'fails') {
                    continue;
                }

                $path = substr($inFile, $pathOffset);
                $code = File::getContents($inFile);
                $expected = File::getContents($outFile);

                yield "[{$format}] {$path}" => [$expected, $code, $formatter];
            }
        }
    }

    /**
     * Iterate over files in 'tests/fixtures/Formatter/in' and map them to
     * pathnames in 'tests/fixtures/Formatter/out/<format>'
     *
     * @param string $format The format under test, i.e. one of the keys
     * returned by {@see FormatterTest::getFileFormats()}.
     * @return Generator<SplFileInfo,string>
     */
    public static function getFiles(string $format): Generator
    {
        return self::doGetFiles($format);
    }

    /**
     * Iterate over files in 'tests/fixtures/Formatter/in' and map them to
     * pathnames in 'tests/fixtures/Formatter/out/<format>' without excluding
     * incompatible files
     *
     * Each input file is mapped to an array that contains an output path at
     * index 0 and, if running on a version of PHP lower than the target
     * version, a path for version-specific output at index 1, should it be
     * necessary.
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
            /** @var string[] */
            $index = array_merge(...array_filter(
                Json::parseObjectAsArray(File::getContents($indexPath)),
                fn(int $key) =>
                    \PHP_VERSION_ID < $key,
                \ARRAY_FILTER_USE_KEY
            ));
            $index = Arr::toIndex($index);
        }

        $versionSuffix =
            \PHP_VERSION_ID < 80000
                ? '.PHP74'
                : (\PHP_VERSION_ID < 80100
                    ? '.PHP80'
                    : (\PHP_VERSION_ID < 80200
                        ? '.PHP81'
                        : (\PHP_VERSION_ID < 80300
                            ? '.PHP82'
                            : null)));

        // Include:
        // - .php files
        // - files with no extension, and
        // - either of the above with a .fails extension
        $files = File::find()
                     ->in($inDir)
                     ->include('/(\.php|\/[^.\/]+)(\.fails)?$/');

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $inFile = (string) $file;
            $path = substr($inFile, $pathOffset);

            if ($index[$path] ?? false) {
                continue;
            }

            $outFile = Pcre::replace('/\.fails$/', '', "$outDir/$path");
            if ($versionSuffix) {
                $versionOutFile = Pcre::replace('/(?<!\G)(\.php)?$/', "$versionSuffix\$1", $outFile);
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
     * Get formats applied to files in 'tests/fixtures/Formatter/in'
     *
     * @return array<string,Formatter>
     */
    public static function getFileFormats(): array
    {
        return [
            '01-default' =>
                Formatter::build()
                    ->go(),
            '02-aligned' =>
                Formatter::build()
                    ->enable([
                        AlignData::class,
                        AlignChains::class,
                        AlignComments::class,
                        AlignArrowFunctions::class,
                        AlignLists::class,
                        AlignTernaryOperators::class,
                    ])
                    ->go(),
            '03-tab' =>
                Formatter::build()
                    ->insertSpaces(false)
                    ->tabSize(8)
                    ->go(),
            '04-psr12' =>
                Formatter::build()
                    ->tokenTypeIndex((new TokenTypeIndex())->withLeadingOperators())
                    ->importSortOrder(ImportSortOrder::NONE)
                    ->psr12()
                    ->go(),
        ];
    }

    public static function getMinVersionIndexPath(): string
    {
        return self::getFixturesPath() . '/versions.json';
    }

    public static function getInputFixturesPath(): string
    {
        return self::getFixturesPath() . '/in';
    }

    public static function getOutputFixturesPath(string $format): string
    {
        return self::getFixturesPath() . "/out/{$format}";
    }

    public static function getFixturesPath(string $class = __CLASS__): string
    {
        return parent::getFixturesPath($class);
    }
}
