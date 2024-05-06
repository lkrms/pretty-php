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
    public function testOutput(string $expected, string $code, $formatter): void
    {
        $this->assertFormatterOutputIs($expected, $code, $formatter);
    }

    /**
     * @return array<array{string,string,Formatter|FormatterB}>
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
        ];
    }
}
