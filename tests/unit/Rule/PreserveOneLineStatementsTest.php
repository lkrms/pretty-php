<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\PreserveOneLineStatements;
use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;

final class PreserveOneLineStatementsTest extends TestCase
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
     * @return iterable<array{string,string,Formatter|FormatterB}>
     */
    public static function outputProvider(): iterable
    {
        $formatterB = Formatter::build()
                          ->enable([PreserveOneLineStatements::class]);
        $formatter = $formatterB->build();

        yield from [
            [
                <<<'PHP'
<?php
class Foo extends Bar { public function baz() { return 71; } }

function foo() { return bar(); }

$foo = function () { return bar(); };
do foo(); while (bar());
do
    foo();
while (bar() ||
    baz());
do { foo(); } while (bar());
do {
    foo();
} while (bar() ||
    baz());
switch ($op) {
    default:
    case '=':
    case '==': return $a == $b;
    case '!=':
    case '<>': return $a != $b;
    case '<': return $a < $b;
    case '>': return $a > $b;
    case '<=': return $a <= $b;
    case '>=': return $a >= $b;
    case '===': return $a === $b;
    case '!==': return $a !== $b;
    case '<=>': return $a <=> $b;
}

PHP,
                <<<'PHP'
<?php
class Foo extends Bar { public function baz() { return 71; } }
function foo() { return bar(); }
$foo = function () { return bar(); };
do foo(); while (bar());
do foo(); while (bar() ||
    baz());
do { foo(); } while (bar());
do { foo(); } while (bar() ||
    baz());
switch ($op) {
    default: case '=': case '==': return $a == $b;
    case '!=': case '<>': return $a != $b;
    case '<': return $a < $b;
    case '>': return $a > $b;
    case '<=': return $a <= $b;
    case '>=': return $a >= $b;
    case '===': return $a === $b;
    case '!==': return $a !== $b;
    case '<=>': return $a <=> $b;
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
if ($foo) { foo(); }  // comment
elseif ($bar) { bar(); }  // comment
else { baz(); }  // comment

// comment
if ($foo) { foo(); }
elseif ($bar) { bar(); }
else { baz(); }

if ($foo) { foo(); }
elseif ($bar) { bar(); }
else if ($baz) { baz(); }
else { qux(); }

if ($foo) { foo(); }
if ($foo) { foo(); } else { bar(); }
if ($foo) {
    foo();
} else {
    bar();
}

try { foo(); }
catch (LogicException $ex) { bar(); }
catch (Throwable $ex) { baz(); }
finally { qux(); }

PHP,
                <<<'PHP'
<?php
if ($foo) {foo();} // comment
elseif ($bar) {bar();} // comment
else {baz();} // comment
// comment
if ($foo) {foo();} elseif ($bar) {bar();}
else {baz();}
if ($foo) {foo();}
elseif ($bar) {bar();} else if ($baz) {baz();} else {qux();}
if ($foo) {foo();}
if ($foo) {foo();} else {bar();}
if ($foo) {foo();} else {
    bar();
}
try {foo();}
catch (LogicException $ex) {bar();}
catch (Throwable $ex) {baz();}
finally {qux();}
PHP,
                $formatter,
            ],
        ];

        if (\PHP_VERSION_ID < 80000) {
            return;
        }

        yield from [
            [
                <<<'PHP'
<?php
#[A] #[B] class Foo extends Bar { public function baz() { return 71; } }

#[A]
#[B]
class Foo extends Bar { public function baz() { return 71; } }

#[A]
#[B]
class Foo extends Bar { public function baz() { return 71; } }

PHP,
                <<<'PHP'
<?php
#[A] #[B] class Foo extends Bar { public function baz() { return 71; } }
#[A] #[B]
class Foo extends Bar { public function baz() { return 71; } }
#[A]
#[B] class Foo extends Bar { public function baz() { return 71; } }
PHP,
                $formatter,
            ],
        ];
    }
}
