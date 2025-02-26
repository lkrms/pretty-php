<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Rule\AlignArrowFunctions;
use Lkrms\PrettyPHP\Rule\AlignChains;
use Lkrms\PrettyPHP\Rule\AlignComments;
use Lkrms\PrettyPHP\Rule\AlignData;
use Lkrms\PrettyPHP\Rule\AlignLists;
use Lkrms\PrettyPHP\Rule\AlignTernaryOperators;
use Lkrms\PrettyPHP\Rule\PreserveOneLineStatements;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;
use Lkrms\PrettyPHP\TokenIndex;
use Salient\Utility\File;
use Salient\Utility\Json;
use Salient\Utility\Regex;
use SplFileInfo;

final class FormatterTest extends TestCase
{
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
     * @return iterable<array{string,string,Formatter|FormatterB}>
     */
    public static function formatProvider(): iterable
    {
        $formatterB = Formatter::build();
        $formatter = $formatterB->build();

        yield from [
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
foo();

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
foo();
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
            'anonymous class commas in array scope' => [
                <<<'PHP'
<?php
$foo = new Bar(
    [
        'baz' => [
            new class implements Foo, Bar {
                public const FOO = 1;
            },
            'qux',
        ],
    ],
);

PHP,
                <<<'PHP'
<?php
$foo = new Bar(
[
'baz' => [
new class implements Foo, Bar {
public const FOO = 1;
},
'qux',
],
],
);
PHP,
                $formatter,
            ],
        ];

        if (\PHP_VERSION_ID < 80400) {
            return;
        }

        yield from [
            'property hooks with and without attributes and comments' => [
                <<<'PHP'
<?php
class Foo
{
    public $A {
        get {
            return 71;
        }
        set {
            echo $value;
        }
    }

    private $B {
        get => 71;
        set => $value;
    }

    abstract $C { &get; set; }

    public $D {
        final get {
            return 71;
        }
        set (string $value) {}
    }

    public $E {
        #[A] get {
            return 71;
        }
        #[B] #[C] set {
            echo $value;
        }
    }

    private $F {
        #[A] get => 71;
        #[B] #[C] set => $value;
    }

    abstract $G {
        #[A] &get;
        #[B] #[C] set;
    }

    public $H {
        #[A] final get {
            return 71;
        }
        #[B] #[C] set (string $value) {}
    }

    public $I {
        #[A]
        get {
            return 71;
        }

        #[B]
        #[C]
        set {
            echo $value;
        }
    }

    private $J {
        #[A]
        get => 71;

        #[B]
        #[C]
        set => $value;
    }

    abstract $K {
        #[A]
        &get;

        #[B]
        #[C]
        set;
    }

    public $L {
        #[A]
        final get {
            return 71;
        }

        #[B]
        #[C]
        set (string $value) {}
    }

    public $M {
        /** @var int */
        #[A] get {
            return 71;
        }
        #[B] #[C] set {
            echo $value;
        }
    }

    private $N {
        /** @var int */
        #[A] get => 71;
        #[B] #[C] set => $value;
    }

    abstract $O {
        /**
         * DocBlock
         */
        #[A] &get;
        #[B] #[C] set;
    }

    public $P {
        /** @var int */
        #[A] final get {
            return 71;
        }
        #[B] #[C] set (string $value) {}
    }

    public $Q {
        final &get => $this->Q;
    }

    public $R {
        // Comment
        get {
            return 71;
        }
        set {
            echo $value;
        }
    }

    public $S {
        get {
            return 71;
        }
        // Comment
        set {
            echo $value;
        }
    }

    public $T {
        // Comment
        get {
            return 71;
        }
        // Comment
        set {
            echo $value;
        }
    }

    public $U {
        get {
            return 71;
        }

        set (
            string $value
        ) {}
    }
}

PHP,
                <<<'PHP'
<?php
class Foo {
    public $A {
        get { return 71; }

        set { echo $value; }
    }
    private $B {
        get => 71;

        set => $value;
    }
    abstract $C {
        &get;

        set;
    }
    public $D {
        final get { return 71; }

        set (string $value) {}
    }
    public $E {
        #[A] get { return 71; }

        #[B] #[C] set { echo $value; }
    }
    private $F {
        #[A] get => 71;

        #[B] #[C] set => $value;
    }
    abstract $G {
        #[A] &get;

        #[B] #[C] set;
    }
    public $H {
        #[A] final get { return 71; }

        #[B] #[C] set (string $value) {}
    }
    public $I {
        #[A] get { return 71; }
        #[B] #[C]
        set { echo $value; }
    }
    private $J {
        #[A] get => 71;
        #[B] #[C]
        set => $value;
    }
    abstract $K { #[A] &get; #[B] #[C]
    set; }
    public $L {
        #[A] final get { return 71; }
        #[B] #[C]
        set (string $value) {}
    }
    public $M {
        /** @var int */ #[A] get { return 71; }

        #[B] #[C] set { echo $value; }
    }
    private $N {
        /** @var int */ #[A] get => 71;

        #[B] #[C] set => $value;
    }
    abstract $O {
        /** DocBlock */ #[A] &get;

        #[B] #[C] set;
    }
    public $P {
        /** @var int */ #[A] final get { return 71; }

        #[B] #[C] set (string $value) {}
    }
    public $Q {
        final &get => $this->Q;
    }
    public $R {
        // Comment
        get { return 71; }

        set { echo $value; }
    }
    public $S {
        get { return 71; }

        // Comment
        set { echo $value; }
    }
    public $T {
        // Comment
        get { return 71; }

        // Comment
        set { echo $value; }
    }
    public $U {
        get { return 71; }
        set (
            string $value
        ) {}
    }
}
PHP,
                $formatter,
            ],
            'property hooks with PreserveOneLineStatements' => [
                <<<'PHP'
<?php
class Foo
{
    public $A {
        get { return 71; }
        set { echo $value; }
    }

    public $B {
        #[A] get { return 71; }
        #[B] #[C] set { echo $value; }
    }

    public $C {
        /** @var int */
        #[A] get { return 71; }
        #[B] #[C] set { echo $value; }
    }

    private $D { get => 71; set => $value; }
    private $E { #[A] get => 71; #[B] #[C] set => $value; }
    private $F { /** @var int */ #[A] get => 71; #[B] #[C] set => $value; }
    public $G { final get { return 71; } set (string $value) {} }
    public $H { #[A] final get { return 71; } #[B] #[C] set (string $value) {} }
    public $I { /** @var int */ #[A] final get { return 71; } #[B] #[C] set (string $value) {} }
    abstract $J { #[A] &get; #[B] #[C] set; }
    abstract $K { /* comment */ #[A] &get; #[B] #[C] set; }
}

PHP,
                <<<'PHP'
<?php
class Foo {
    public $A {
        get { return 71; }
        set { echo $value; }
    }
    public $B {
        #[A] get { return 71; }
        #[B] #[C] set { echo $value; }
    }
    public $C {
        /** @var int */
        #[A] get { return 71; }
        #[B] #[C] set { echo $value; }
    }
    private $D { get => 71; set => $value; }
    private $E { #[A] get => 71; #[B] #[C] set => $value; }
    private $F { /** @var int */ #[A] get => 71; #[B] #[C] set => $value; }
    public $G { final get { return 71; } set (string $value) {} }
    public $H { #[A] final get { return 71; } #[B] #[C] set (string $value) {} }
    public $I { /** @var int */ #[A] final get { return 71; } #[B] #[C] set (string $value) {} }
    abstract $J { #[A] &get; #[B] #[C] set; }
    abstract $K { /* comment */ #[A] &get; #[B] #[C] set; }
}
PHP,
                $formatterB->withExtensions([PreserveOneLineStatements::class]),
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
     * @return iterable<array{string,string,Formatter}>
     */
    public static function filesProvider(): iterable
    {
        $pathOffset = strlen(self::getInputFixturesPath()) + 1;
        foreach (self::getFileFormats() as $dir => $formatter) {
            $format = substr($dir, 3);
            foreach (self::getFiles($dir) as $file => $outFile) {
                $inFile = (string) $file;
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
     * @return iterable<SplFileInfo,string>
     */
    public static function getFiles(string $format): iterable
    {
        return self::doGetFiles($format);
    }

    /**
     * Iterate over files in 'tests/fixtures/Formatter/in' and map them to
     * pathnames in 'tests/fixtures/Formatter/out/<format>' without excluding
     * incompatible files
     *
     * Each input file is mapped to an array that contains an output path and a
     * path for version-specific output, should it be necessary.
     *
     * @return iterable<SplFileInfo,array{string,string}>
     */
    public static function getAllFiles(string $format): iterable
    {
        return self::doGetFiles($format, true);
    }

    /**
     * @phpstan-return (
     *     $all is false
     *     ? iterable<SplFileInfo,string>
     *     : iterable<SplFileInfo,array{string,string}>
     * )
     */
    private static function doGetFiles(string $format, bool $all = false): iterable
    {
        $inDir = self::getInputFixturesPath();
        $outDir = self::getOutputFixturesPath($format);
        $pathOffset = strlen($inDir) + 1;

        $index = [];
        if (!$all && is_file($indexPath = self::getIndexFixturePath())) {
            /** @var array<int,string[]> */
            $index = Json::objectAsArray(File::getContents($indexPath));
            $index = $index[\PHP_VERSION_ID - \PHP_VERSION_ID % 100] ?? [];
            $index = array_fill_keys($index, true);
        }

        $versionSuffix = '.PHP'
            . (int) (\PHP_VERSION_ID / 10000)
            . (int) (\PHP_VERSION_ID % 10000 / 100);

        // Find .php files and files with no extension
        $files = File::find()
                     ->files()
                     ->in($inDir)
                     ->include('/(\.php|\/[^.\/]+)$/');

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $inFile = (string) $file;
            $path = substr($inFile, $pathOffset);

            if ($index[$path] ?? false) {
                continue;
            }

            $outFile = "$outDir/$path";
            $versionOutFile = Regex::replace('/(\.php|(?<!\.php))$/', "$versionSuffix\$1", $outFile);

            yield $file => $all
                ? [$outFile, $versionOutFile]
                : (file_exists($versionOutFile) ? $versionOutFile : $outFile);
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
                    ->build(),
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
                    ->build(),
            '03-tab' =>
                Formatter::build()
                    ->insertSpaces(false)
                    ->tabSize(8)
                    ->build(),
            '04-psr12' =>
                Formatter::build()
                    ->tokenIndex(new TokenIndex(true))
                    ->importSortOrder(ImportSortOrder::NONE)
                    ->indentBetweenTags()
                    ->psr12()
                    ->build(),
        ];
    }

    public static function getIndexFixturePath(): string
    {
        return self::getFixturesPath(__CLASS__) . '/index.json';
    }

    public static function getInputFixturesPath(): string
    {
        return self::getFixturesPath(__CLASS__) . '/in';
    }

    public static function getOutputFixturesPath(string $format): string
    {
        return self::getFixturesPath(__CLASS__) . "/out/{$format}";
    }
}
