<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Filter\SortImports;
use Lkrms\PrettyPHP\Rule\PreserveOneLineStatements;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;

final class DeclarationSpacingTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider outputProvider
     *
     * @param Formatter|FormatterB $formatter
     */
    public function testOutput(string $expected, string $code, $formatter, ?string $tightExpected = null): void
    {
        if ($formatter instanceof FormatterB) {
            $formatter = $formatter->go();
        }

        $this->assertFormatterOutputIs($expected, $code, $formatter);

        $formatter = $formatter->with('TightDeclarationSpacing', true);
        $this->assertFormatterOutputIs($tightExpected ?? $expected, $code, $formatter);
    }

    /**
     * @return array<array{string,string,Formatter|FormatterB,3?:string|null}>
     */
    public static function outputProvider(): array
    {
        $formatterB = Formatter::build();
        $formatter = $formatterB->go();

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
                $formatter,
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
            ],
            [
                <<<'PHP'
<?php
interface I { function foo(); }

class A { function foo() {} }
class B { function foo() {} }
class C { function foo() {} }

PHP,
                <<<'PHP'
<?php
interface I{function foo();} class A{function foo(){}} class B{function foo(){}} class C{function foo(){}}
PHP,
                $formatterB->enable([PreserveOneLineStatements::class]),
            ],
            [
                <<<'PHP'
<?php
class Foo
{
    public const A = 'a';
    /** @var string[] */
    public const B = [
        'b',
    ];

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
}

PHP,
                <<<'PHP'
<?php
class Foo
{
    public const A = 'a';
    /**
     * @var string[]
     */
    public const B = [
        'b',
    ];
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
                $formatterB->enable([PreserveOneLineStatements::class]),
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
PHP,
                $formatter,
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

PHP,
            ],
        ];
    }
}
