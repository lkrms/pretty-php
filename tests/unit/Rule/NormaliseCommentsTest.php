<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Tests\TestCase;

final class NormaliseCommentsTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code);
    }

    /**
     * @return array<array{string,string}>
     */
    public static function outputProvider(): array
    {
        return [
            'text aligned with *' => [
                <<<'PHP'
<?php

/**
 * Comment
 *
 * @api
 */
class Foo
{
    /**
     * Comment
     *
     * @api
     */
    function bar() {}
}

PHP,
                <<<'PHP'
<?php
/**
 * Comment

 @api
 */
class Foo {
    /**
     * Comment

     @api
     */
    function bar() {}
}
PHP,
            ],
            'indentation removed' => [
                <<<'PHP'
<?php

/**
 * Comment
 *
 * @api
 */
class Foo
{
    /**
     * Comment
     *
     * @api
     */
    function bar() {}
}

PHP,
                <<<'PHP'
<?php
/**
* Comment

@api
*/
class Foo {
    /**
    * Comment

    @api
    */
    function bar() {}
}
PHP,
            ],
            'text indented from *' => [
                <<<'PHP'
<?php

/**
 * Comment
 *
 * @api
 */
class Foo
{
    /**
     * Comment
     *
     * @api
     */
    function bar() {}
}

PHP,
                <<<'PHP'
<?php
/**
 * Comment

  @api
 */
class Foo {
    /**
     * Comment

      @api
     */
    function bar() {}
}
PHP,
            ],
            'text aligned with *, last * misaligned' => [
                <<<'PHP'
<?php

/**
 * Comment
 *
 * @api
 */
class Foo
{
    /**
     * Comment
     *
     * @api
     */
    function bar() {}
}

PHP,
                <<<'PHP'
<?php
/**
 * Comment

 @api
*/
class Foo {
    /**
     * Comment

     @api
    */
    function bar() {}
}
PHP,
            ],
            'list with * not indented' => [
                <<<'PHP'
<?php

/**
 * Comment
 *
 * List:
 * * Item 1
 * * Item 2
 */
class Foo
{
    /**
     * Comment
     *
     * List:
     * * Item 1
     * * Item 2
     */
    function bar() {}
}

PHP,
                <<<'PHP'
<?php
/**
 * Comment

List:
* Item 1
* Item 2

*/
class Foo {
    /**
     * Comment

    List:
    * Item 1
    * Item 2

    */
    function bar() {}
}
PHP,
            ],
            'indented code #1' => [
                <<<'PHP'
<?php

/*
 * if ($foo) {
 *     bar();
 * }
 */
class Foo
{
    /*
     * if ($foo) {
     *     bar();
     * }
     */
    function bar() {}
}

PHP,
                <<<'PHP'
<?php
/*
    if ($foo) {
        bar();
    }
*/
class Foo {
    /*
        if ($foo) {
            bar();
        }
    */
    function bar() {}
}
PHP,
            ],
            'indented code #2' => [
                <<<'PHP'
<?php

/*
 * if ($foo):
 * bar();
 *     endif;
 */
class Foo
{
    /*
     * if ($foo):
     * bar();
     *     endif;
     */
    function bar() {}
}

PHP,
                <<<'PHP'
<?php
/*
        if ($foo):
    bar();
        endif;
*/
class Foo {
    /*
            if ($foo):
        bar();
            endif;
    */
    function bar() {}
}
PHP,
            ],
            'indented code #3' => [
                <<<'PHP'
<?php
/* if ($foo):
       bar();
   endif; */
class Foo
{
    /* if ($foo):
           bar();
       endif; */
    function bar() {}
}

PHP,
                <<<'PHP'
<?php
/* if ($foo):
       bar();
   endif; */
class Foo {
    /* if ($foo):
           bar();
       endif; */
    function bar() {}
}
PHP,
            ],
            'indented code #4' => [
                <<<'PHP'
<?php

/*
 * Example:
 *
 *  if ($foo) {
 *      bar();
 *  }
 */
class Foo
{
    /*
     * Example:
     *
     *  if ($foo) {
     *      bar();
     *  }
     */
    function bar() {}
}

PHP,
                <<<'PHP'
<?php
/*
 * Example:

    if ($foo) {
        bar();
    }
 */
class Foo {
    /*
     * Example:

        if ($foo) {
            bar();
        }
     */
    function bar() {}
}
PHP,
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    public function bar(): array
    {
        /** @var int|null */
        static $baz;
        return [];
    }
}

PHP,
                <<<'PHP'
<?php
class Foo {
    public function bar(): array
    {
        /**
         * @var int|null
         */
        static $baz;
        return [];
    }
}
PHP,
            ],
        ];
    }
}
