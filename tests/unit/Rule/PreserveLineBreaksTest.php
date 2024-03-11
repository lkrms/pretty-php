<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\PreserveLineBreaks;
use Generator;

final class PreserveLineBreaksTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code, bool $preserve = true): void
    {
        $this->assertCodeFormatIs(
            $expected,
            $code,
            [],
            $preserve
                ? []
                : [PreserveLineBreaks::class]
        );
    }

    /**
     * @return Generator<string,array{string,string,2?:bool}>
     */
    public static function outputProvider(): Generator
    {
        yield 'logical operator after bracket' => [
            <<<'PHP'
<?php
return a($b) &&
    a($c) &&
    strcmp((string) $b, (string) $c) === 0;

PHP,
            <<<'PHP'
<?php
return a($b) && a($c)
    && strcmp((string) $b, (string) $c) === 0;
PHP,
        ];

        yield 'newline after null coalesce assignment operator' => [
            <<<'PHP'
<?php
class A
{
    private static $b = [];

    public static function foo()
    {
        $foo = self::$b[static::class]
            ??= self::getFoo();
    }

    private static function getFoo()
    {
        return 'bar';
    }
}

PHP,
            <<<'PHP'
<?php
class A
{
    private static $b = [];
    public static function foo()
    {
        $foo = self::$b[static::class] ??=
            self::getFoo();
    }
    private static function getFoo()
    {
        return 'bar';
    }
}
PHP,
        ];

        if (\PHP_VERSION_ID < 80000) {
            return;
        }

        $blankLines = <<<'PHP'
<?php

class Foo

{

    public const BAR =

        71;

    private $Baz;

    private $Qux;

    private $Quux;

    public function __construct(

        $baz

    )

    {

        $this->Baz =

            $baz;

        $this->Qux = [

            'alpha' => 'a',

            'bravo' => 'b',

            'charlie' => 'c'

        ];

        $c = 0;

        foreach ($this->Qux as $key => $value) {

            echo "$key: $value";

            $this->Quux[$key] = ord($value);

            if (

                $this->Quux[

                    $key

                ] % 5

                    ||

                    $key === 'bravo'

            )

            {

                $c++;

            }

        }

        return

            match ($c) {

                0,

                =>

                    $this->Baz,

                1,

                2

                =>

                    $this->baz *

                        2,

                default

                =>

                    0,

            };

    }

}


PHP;

        yield 'blank lines #1' => [
            <<<'PHP'
<?php

class Foo
{
    public const BAR =
        71;

    private $Baz;

    private $Qux;

    private $Quux;

    public function __construct(
        $baz
    ) {
        $this->Baz =
            $baz;

        $this->Qux = [
            'alpha' => 'a',
            'bravo' => 'b',
            'charlie' => 'c'
        ];

        $c = 0;

        foreach ($this->Qux as $key => $value) {
            echo "$key: $value";

            $this->Quux[$key] = ord($value);

            if (
                $this->Quux[
                    $key
                ] % 5 ||
                $key === 'bravo'
            ) {
                $c++;
            }
        }

        return
            match ($c) {
                0, =>
                    $this->Baz,

                1,
                2 =>
                    $this->baz
                    * 2,

                default =>
                    0,
            };
    }
}

PHP,
            $blankLines,
        ];

        yield 'blank lines #2' => [
            <<<'PHP'
<?php

class Foo
{
    public const BAR = 71;

    private $Baz;

    private $Qux;

    private $Quux;

    public function __construct($baz)
    {
        $this->Baz = $baz;

        $this->Qux = ['alpha' => 'a', 'bravo' => 'b', 'charlie' => 'c'];

        $c = 0;

        foreach ($this->Qux as $key => $value) {
            echo "$key: $value";

            $this->Quux[$key] = ord($value);

            if ($this->Quux[$key] % 5 || $key === 'bravo') {
                $c++;
            }
        }

        return match ($c) {
            0, => $this->Baz,

            1, 2 => $this->baz * 2,

            default => 0,
        };
    }
}

PHP,
            $blankLines,
            false,
        ];

        $blankLinesWithComments = <<<'PHP'
<?php

//

class Foo

//

{

    //

    public const BAR =

        //

        71;

    //

    private $Baz;

    //

    private $Qux;

    //

    private $Quux;

    //

    public function __construct(

        //

        $baz

        //

    )

    //

    {

        //

        $this->Baz =

            //

            $baz;

        //

        $this->Qux = [

            //

            'alpha' => 'a',

            //

            'bravo' => 'b',

            //

            'charlie' => 'c'

            //

        ];

        //

        $c = 0;

        //

        foreach ($this->Qux as $key => $value) {

            //

            echo "$key: $value";

            //

            $this->Quux[$key] = ord($value);

            //

            if (

                //

                $this->Quux[

                    //

                    $key

                    //

                ] % 5

                //

                ||

                //

                $key === 'bravo'

            )

            //

            {

                //

                $c++;

                //

            }

            //

        }

        //

        return

            //

            match ($c) {

                //

                0,

                //

                =>

                    //

                    $this->Baz,

                //

                1,

                //

                2

                    //

                    =>

                        //

                        $this->baz
                        *

                        //

                        2,

                //

                default

                    //

                    =>

                        //

                        0,

                //

            };

        //

    }

    //

}

//


PHP;

        yield 'blank lines with comments #1' => [
            <<<'PHP'
<?php

//

class Foo
//
{
    //

    public const BAR =
        //
        71;

    //

    private $Baz;

    //

    private $Qux;

    //

    private $Quux;

    //

    public function __construct(
        //
        $baz
        //
    )
    //
    {
        //

        $this->Baz =
            //
            $baz;

        //

        $this->Qux = [
            //
            'alpha' => 'a',
            //
            'bravo' => 'b',
            //
            'charlie' => 'c'
            //
        ];

        //

        $c = 0;

        //

        foreach ($this->Qux as $key => $value) {
            //

            echo "$key: $value";

            //

            $this->Quux[$key] = ord($value);

            //

            if (
                //
                $this->Quux[
                    //
                    $key
                    //
                ] % 5 ||
                //
                //
                $key === 'bravo'
            )
            //
            {
                //

                $c++;

                //
            }

            //
        }

        //

        return
            //
            match ($c) {
                //
                0,
                //
                =>
                    //
                    $this->Baz,

                //
                1,
                //
                2
                    //
                    =>
                        //
                        $this->baz
                        //
                        * 2,

                //
                default
                    //
                    =>
                        //
                        0,

                //
            };

        //
    }

    //
}

//

PHP,
            $blankLinesWithComments,
        ];

        yield 'blank lines with comments #2' => [
            <<<'PHP'
<?php

//

class Foo
//
{
    //

    public const BAR =
        //
        71;

    //

    private $Baz;

    //

    private $Qux;

    //

    private $Quux;

    //

    public function __construct(
        //
        $baz
        //
    )
    //
    {
        //

        $this->Baz =
            //
            $baz;

        //

        $this->Qux = [
            //
            'alpha' => 'a',
            //
            'bravo' => 'b',
            //
            'charlie' => 'c'
            //
        ];

        //

        $c = 0;

        //

        foreach ($this->Qux as $key => $value) {
            //

            echo "$key: $value";

            //

            $this->Quux[$key] = ord($value);

            //

            if (
                //
                $this->Quux[
                    //
                    $key
                    //
                ] % 5 ||
                //
                //
                $key === 'bravo'
            )
            //
            {
                //

                $c++;

                //
            }

            //
        }

        //

        return
            //
            match ($c) {
                //
                0,
                //
                =>
                    //
                    $this->Baz,

                //
                1,
                //
                2
                    //
                    =>
                        //
                        $this->baz
                        //
                        * 2,

                //
                default
                    //
                    =>
                        //
                        0,

                //
            };

        //
    }

    //
}

//

PHP,
            $blankLinesWithComments,
            false,
        ];
    }
}
