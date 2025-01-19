<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Filter\SortImports;
use Lkrms\PrettyPHP\Rule\PreserveOneLineStatements;
use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;

final class DeclarationSpacingTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     *
     * @param Formatter|FormatterB $formatter
     */
    public function testOutput(string $expected, ?string $tightExpected, string $code, $formatter): void
    {
        if ($formatter instanceof FormatterB) {
            $formatter = $formatter->build();
        }

        $this->assertFormatterOutputIs($expected, $code, $formatter);

        $formatter = $formatter->withTightDeclarationSpacing();
        $this->assertFormatterOutputIs($tightExpected ?? $expected, $code, $formatter);
    }

    /**
     * @return array<array{string,string|null,string,Formatter|FormatterB}>
     */
    public static function outputProvider(): array
    {
        $formatterB = Formatter::build();
        $formatter = $formatterB->build();

        $input = <<<'PHP'
<?php declare(strict_types=1);
namespace Foo\Bar;
use const PREG_UNMATCHED_AS_NULL;
use const PREG_SET_ORDER;
use function substr;
use function in_array;
use Qux\Factory;
use Foo\Exception\InvalidValueException;
/**
 * Summary
 */
class Foo
{
    public int $Bar;
    public string $Qux;
    /**
     * @var string[]
     */
    public array $Quux = [];
    public function __construct(int $bar, string $qux)
    {
        $this->Bar = -1;
        $this->Qux = $qux;
    }
}
PHP;

        $tightOutput = <<<'PHP'
<?php
class Foo
{
    public const A = 'a';
    /** @var string */
    public const B = 'b';

    /**
     * Comment
     */
    public const C = 'c';

    public const D = 'd';
    public const E = 'e';
    // Comment
    // Comment
    public const F = 'f';

    // Comment

    // Comment
    public const G = 'g';
    public const H = 'h';
    public const I = 'i';

    // Comment

    public const J = 'j';
    public const K = 'k';
    public const L = 'l';
    // Comment
    public const M = 'm';
    public const N = 'n';
    /** @var string */
    public const O = 'o';
    // Comment
    public const P = 'p';
    public const Q = 'q';

    /**
     * Comment
     */
    public const R = 'r';

    // Comment

    public const S = 's';
    public const T = 't';
    /** @var string */
    // Comment
    public const U = 'u';
    public const V = 'v';

    /**
     * Comment
     */
    // Comment
    public const W = 'w';

    public const X = 'x';

    /**
     * Comment
     */
    public const Y = 'y';

    // Comment
    public const Z = 'z';
}

PHP;

        return [
            [
                <<<'PHP'
<?php declare(strict_types=1);

namespace Foo\Bar;

use Foo\Exception\InvalidValueException;
use Qux\Factory;

use function in_array;
use function substr;

use const PREG_SET_ORDER;
use const PREG_UNMATCHED_AS_NULL;

/**
 * Summary
 */
class Foo
{
    public int $Bar;
    public string $Qux;
    /** @var string[] */
    public array $Quux = [];

    public function __construct(int $bar, string $qux)
    {
        $this->Bar = -1;
        $this->Qux = $qux;
    }
}

PHP,
                null,
                $input,
                $formatter,
            ],
            [
                <<<'PHP'
<?php declare(strict_types=1);

namespace Foo\Bar;

use const PREG_UNMATCHED_AS_NULL;
use const PREG_SET_ORDER;
use function substr;
use function in_array;
use Qux\Factory;
use Foo\Exception\InvalidValueException;

/**
 * Summary
 */
class Foo
{
    public int $Bar;
    public string $Qux;
    /** @var string[] */
    public array $Quux = [];

    public function __construct(int $bar, string $qux)
    {
        $this->Bar = -1;
        $this->Qux = $qux;
    }
}

PHP,
                null,
                $input,
                $formatterB->disable([SortImports::class]),
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    /**
     * @var string[]
     */
    public array $Bar = [];

    /**
     * Summary
     */
    public function __construct(int $bar, string $qux)
    {
        $this->Qux = $qux;
        $this->Quux = -1;
    }
}

PHP,
                <<<'PHP'
<?php
class Foo
{
    /** @var string[] */
    public array $Bar = [];

    /**
     * Summary
     */
    public function __construct(int $bar, string $qux)
    {
        $this->Qux = $qux;
        $this->Quux = -1;
    }
}

PHP,
                <<<'PHP'
<?php
class Foo
{
    /**
     * @var string[]
     */
    public array $Bar = [];
    /**
     * Summary
     */
    public function __construct(int $bar, string $qux)
    {
        $this->Qux = $qux;
        $this->Quux = -1;
    }
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    /** @var string[] */
    public array $Bar = [];
    public string $Qux;
    public int $Quux;

    /**
     * Summary
     */
    public function __construct(int $bar, string $qux)
    {
        $this->Qux = $qux;
        $this->Quux = -1;
    }
}

PHP,
                null,
                <<<'PHP'
<?php
class Foo
{
    /**
     * @var string[]
     */
    public array $Bar = [];
    public string $Qux;
    public int $Quux;
    /**
     * Summary
     */
    public function __construct(int $bar, string $qux)
    {
        $this->Qux = $qux;
        $this->Quux = -1;
    }
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    /**
     * @var string[]
     */
    public array $Bar = [];

    public string $Qux;

    /**
     * Summary
     */
    public int $Quux;

    /**
     * Summary
     */
    public function __construct(int $bar, string $qux)
    {
        $this->Qux = $qux;
        $this->Quux = -1;
    }
}

PHP,
                <<<'PHP'
<?php
class Foo
{
    /** @var string[] */
    public array $Bar = [];
    public string $Qux;

    /**
     * Summary
     */
    public int $Quux;

    /**
     * Summary
     */
    public function __construct(int $bar, string $qux)
    {
        $this->Qux = $qux;
        $this->Quux = -1;
    }
}

PHP,
                <<<'PHP'
<?php
class Foo
{
    /**
     * @var string[]
     */
    public array $Bar = [];

    public string $Qux;
    /**
     * Summary
     */
    public int $Quux;
    /**
     * Summary
     */
    public function __construct(int $bar, string $qux)
    {
        $this->Qux = $qux;
        $this->Quux = -1;
    }
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    public int $Bar;
    public string $Qux;
    /** @var string[] */
    public array $Quux = [];
    /** @var string[] */
    public array $Quuux = [];
}

PHP,
                null,
                <<<'PHP'
<?php
class Foo
{
    public int $Bar;
    public string $Qux;

    /**
     * @var string[]
     */
    public array $Quux = [];

    /**
     * @var string[]
     */
    public array $Quuux = [];
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    public int $Bar;

    public string $Qux;

    /**
     * @var string[]
     */
    public array $Quux = [];

    /**
     * @var string[]
     */
    public array $Quuux = [];
}

PHP,
                <<<'PHP'
<?php
class Foo
{
    public int $Bar;
    public string $Qux;
    /** @var string[] */
    public array $Quux = [];
    /** @var string[] */
    public array $Quuux = [];
}

PHP,
                <<<'PHP'
<?php
class Foo
{
    public int $Bar;

    public string $Qux;
    /** @var string[] */
    public array $Quux = [];
    /** @var string[] */
    public array $Quuux = [];
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
interface I { function foo(); }

class A { function foo() {} }
class B { function foo() {} }
class C { function foo() {} }

PHP,
                null,
                <<<'PHP'
<?php
interface I{function foo();} class A{function foo(){}} class B{function foo(){}} class C{function foo(){}}
PHP,
                $formatterB->enable([PreserveOneLineStatements::class]),
            ],
            [
                $tightOutput,
                null,
                <<<'PHP'
<?php
class Foo
{
    public const A = 'a';
    /**
     * @var string
     */
    public const B = 'b';
    /**
     * Comment
     */
    public const C = 'c';
    public const D = 'd';
    public const E = 'e';
    // Comment
    // Comment
    public const F = 'f';

    // Comment

    // Comment
    public const G = 'g';
    public const H = 'h';
    public const I = 'i';

    // Comment

    public const J = 'j';
    public const K = 'k';
    public const L = 'l';
    // Comment

    public const M = 'm';
    public const N = 'n';
    /**
     * @var string
     */
    public const O = 'o';
    // Comment

    public const P = 'p';
    public const Q = 'q';
    /**
     * Comment
     */
    public const R = 'r';
    // Comment

    public const S = 's';
    public const T = 't';
    /**
     * @var string
     */
    // Comment
    public const U = 'u';
    public const V = 'v';
    /**
     * Comment
     */
    // Comment
    public const W = 'w';
    public const X = 'x';
    /**
     * Comment
     */
    public const Y = 'y';
    // Comment
    public const Z = 'z';
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    public const A = 'a';

    /**
     * @var string
     */
    public const B = 'b';

    /**
     * Comment
     */
    public const C = 'c';

    public const D = 'd';

    public const E = 'e';

    // Comment
    // Comment
    public const F = 'f';

    // Comment

    // Comment
    public const G = 'g';

    public const H = 'h';

    public const I = 'i';

    // Comment

    public const J = 'j';

    public const K = 'k';

    public const L = 'l';

    // Comment

    public const M = 'm';

    public const N = 'n';

    /**
     * @var string
     */
    public const O = 'o';

    // Comment

    public const P = 'p';

    public const Q = 'q';

    /**
     * Comment
     */
    public const R = 'r';

    // Comment

    public const S = 's';

    public const T = 't';

    /**
     * @var string
     */
    // Comment
    public const U = 'u';

    public const V = 'v';

    /**
     * Comment
     */
    // Comment
    public const W = 'w';

    public const X = 'x';

    /**
     * Comment
     */
    public const Y = 'y';

    // Comment
    public const Z = 'z';
}

PHP,
                $tightOutput,
                <<<'PHP'
<?php
class Foo
{
    public const A = 'a';

    /**
     * @var string
     */
    public const B = 'b';
    /**
     * Comment
     */
    public const C = 'c';
    public const D = 'd';
    public const E = 'e';
    // Comment
    // Comment
    public const F = 'f';

    // Comment

    // Comment
    public const G = 'g';
    public const H = 'h';
    public const I = 'i';

    // Comment

    public const J = 'j';
    public const K = 'k';
    public const L = 'l';
    // Comment

    public const M = 'm';
    public const N = 'n';
    /**
     * @var string
     */
    public const O = 'o';
    // Comment

    public const P = 'p';
    public const Q = 'q';
    /**
     * Comment
     */
    public const R = 'r';
    // Comment

    public const S = 's';
    public const T = 't';
    /**
     * @var string
     */
    // Comment
    public const U = 'u';
    public const V = 'v';
    /**
     * Comment
     */
    // Comment
    public const W = 'w';
    public const X = 'x';
    /**
     * Comment
     */
    public const Y = 'y';
    // Comment
    public const Z = 'z';
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
abstract class Foo
{
    public function callBar() { $this->bar(); }
    abstract protected function bar();
    public function baz() {}

    public function qux()
    {
        global $a;
        global $b;
        static $c;
        $this->bar();
        global $d;
        global $e;

        static $f;
        $this->bar();
        global $g;

        global $h;
        static $i;
        $this->bar();
        static $j;
        global $k;
        global $l;

        static $m;
    }

    public function callBar2() { $this->bar2(); }
    abstract protected function bar2();
    public function baz2() {}
}

abstract class Bar
{
    public function callFoo() { $this->foo(); }

    abstract protected function foo();

    public function baz() {}

    public function qux()
    {
        $this->foo();
    }

    public function callFoo2() { $this->foo2(); }

    abstract protected function foo2();

    public function baz2() {}
}

PHP,
                <<<'PHP'
<?php
abstract class Foo
{
    public function callBar() { $this->bar(); }
    abstract protected function bar();
    public function baz() {}

    public function qux()
    {
        global $a;
        global $b;
        static $c;
        $this->bar();
        global $d;
        global $e;

        static $f;
        $this->bar();
        global $g;

        global $h;
        static $i;
        $this->bar();
        static $j;
        global $k;
        global $l;

        static $m;
    }

    public function callBar2() { $this->bar2(); }
    abstract protected function bar2();
    public function baz2() {}
}

abstract class Bar
{
    public function callFoo() { $this->foo(); }
    abstract protected function foo();
    public function baz() {}

    public function qux()
    {
        $this->foo();
    }

    public function callFoo2() { $this->foo2(); }
    abstract protected function foo2();
    public function baz2() {}
}

PHP,
                <<<'PHP'
<?php
abstract class Foo
{
    public function callBar() { $this->bar(); }
    abstract protected function bar();
    public function baz() {}
    public function qux()
    {
        global $a;
        global $b;
        static $c;
        $this->bar();
        global $d;
        global $e;

        static $f;
        $this->bar();
        global $g;

        global $h;
        static $i;
        $this->bar();
        static $j;
        global $k;
        global $l;

        static $m;
    }
    public function callBar2() { $this->bar2(); }
    abstract protected function bar2();
    public function baz2() {}
}
abstract class Bar
{
    public function callFoo() { $this->foo(); }

    abstract protected function foo();
    public function baz() {}
    public function qux()
    {
        $this->foo();
    }
    public function callFoo2() { $this->foo2(); }
    abstract protected function foo2();

    public function baz2() {}
}
PHP,
                $formatterB->enable([PreserveOneLineStatements::class]),
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    public const A = 0;
    public const B = 1;

    private const C = 2;
}

class Bar
{
    public const A = 0;

    private const B = 1;
    private const C = 2;
}

class Baz
{
    public const A = 0;

    protected const B = 1;

    private const C = 2;
    private const D = 3;
}

PHP,
                <<<'PHP'
<?php
class Foo
{
    public const A = 0;
    public const B = 1;
    private const C = 2;
}

class Bar
{
    public const A = 0;
    private const B = 1;
    private const C = 2;
}

class Baz
{
    public const A = 0;
    protected const B = 1;
    private const C = 2;
    private const D = 3;
}

PHP,
                <<<'PHP'
<?php
class Foo {
    public const A = 0;
    public const B = 1;

    private const C = 2;
}
class Bar {
    public const A = 0;

    private const B = 1;
    private const C = 2;
}
class Baz {
    public const A = 0;

    protected const B = 1;

    private const C = 2;
    private const D = 3;
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    /** @var int */
    public const A = 0;
    /** @var int */
    public const B = 1;

    /** @var int */
    private const C = 2;
}

class Bar
{
    /** @var int */
    public const A = 0;

    /** @var int */
    private const B = 1;
    /** @var int */
    private const C = 2;
}

class Baz
{
    /** @var int */
    public const A = 0;

    /** @var int */
    protected const B = 1;

    /** @var int */
    private const C = 2;
    /** @var int */
    private const D = 3;
}

PHP,
                <<<'PHP'
<?php
class Foo
{
    /** @var int */
    public const A = 0;
    /** @var int */
    public const B = 1;
    /** @var int */
    private const C = 2;
}

class Bar
{
    /** @var int */
    public const A = 0;
    /** @var int */
    private const B = 1;
    /** @var int */
    private const C = 2;
}

class Baz
{
    /** @var int */
    public const A = 0;
    /** @var int */
    protected const B = 1;
    /** @var int */
    private const C = 2;
    /** @var int */
    private const D = 3;
}

PHP,
                <<<'PHP'
<?php
class Foo {
    /**
     * @var int
     */
    public const A = 0;
    /**
     * @var int
     */
    public const B = 1;

    /**
     * @var int
     */
    private const C = 2;
}
class Bar {
    /**
     * @var int
     */
    public const A = 0;

    /**
     * @var int
     */
    private const B = 1;
    /**
     * @var int
     */
    private const C = 2;
}
class Baz {
    /**
     * @var int
     */
    public const A = 0;

    /**
     * @var int
     */
    protected const B = 1;

    /**
     * @var int
     */
    private const C = 2;
    /**
     * @var int
     */
    private const D = 3;
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    /**
     * Comment
     */
    private $Bar;

    // Comment

    private $Baz;
}

PHP,
                null,
                <<<'PHP'
<?php
class Foo
{
    /**
     * Comment
     */
    private $Bar;
    // Comment

    private $Baz;
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    private $Bar;

    // Comment

    private $Baz;

    private $Qux;
}

PHP,
                <<<'PHP'
<?php
class Foo
{
    private $Bar;
    // Comment
    private $Baz;
    private $Qux;
}

PHP,
                <<<'PHP'
<?php
class Foo
{
    private $Bar;
    // Comment

    private $Baz;
    private $Qux;
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    /**
     * Comment
     */
    // Comment
    private $Bar;

    private $Baz;
}

PHP,
                null,
                <<<'PHP'
<?php
class Foo
{
    /**
     * Comment
     */
    // Comment
    private $Bar;
    private $Baz;
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    /** @var string */
    // Comment
    private $Bar;
    private $Baz;
}

PHP,
                null,
                <<<'PHP'
<?php
class Foo
{
    /**
     * @var string
     */
    // Comment
    private $Bar;
    private $Baz;
}
PHP,
                $formatter,
            ],
        ];
    }
}
