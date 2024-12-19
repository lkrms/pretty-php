<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Tests\TestCase;

final class ControlStructureSpacingTest extends TestCase
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
            'suppress blank line after body' => [
                <<<'PHP'
<?php
do
    something();
while (false);

PHP,
                <<<'PHP'
<?php
do
something();

while (false);
PHP,
            ],
            [
                <<<'PHP'
<?php
if (true)
    if (false):
    else:
    endif
?><?php
else
?><?php

PHP,
                <<<'PHP'
<?php
if (true)
if (false):
else:
endif
?><?php
else
?><?php
PHP,
            ],
            [
                <<<'PHP'
<?php
if ($foo);
elseif ($bar);
else;

do;
while (foo());

while (foo());

for (;;);

foreach ($foo as $bar);

if ($foo)
    bar();
elseif ($baz)
    qux();
else
    quux();

do
    foo();
while (bar());

do
    while (foo());
while (bar());

while (foo())
    bar();

for (;;)
    foo();

foreach ($foo as $bar)
    baz();

PHP,
                <<<'PHP'
<?php
if ($foo); elseif ($bar); else;

do; while (foo());

while (foo());

for (;;);

foreach ($foo as $bar);

if ($foo) bar(); elseif ($baz) qux(); else quux();

do foo(); while (bar());

do while (foo()); while (bar());

while (foo()) bar();

for (;;) foo();

foreach ($foo as $bar) baz();
PHP,
            ],
            [
                <<<'PHP'
<?php
if ($foo)
    label:
elseif ($bar)
    label:
else
    label:

do
    label:
while (foo());

while (foo())
    label:

for (;;)
    label:

foreach ($foo as $bar)
    label:

PHP,
                <<<'PHP'
<?php
if ($foo)
label:
elseif ($bar)
label:
else
label:

do
label:
while (foo());

while (foo())
label:

for (;;)
label:

foreach ($foo as $bar)
label:
PHP,
            ],
            [
                <<<'PHP'
<?php
do
    do
        if ($foo)
?><?php
        elseif ($bar)
?><?php
        else
?><?php
    while (baz());
while (qux());

do
    if ($foo)
?><?php
while (bar());

while (foo())
    if ($bar)
?><?php

PHP,
                <<<'PHP'
<?php
do
do
if ($foo)
?><?php
elseif ($bar)
?><?php
else
?><?php
while (baz());
while (qux());

do
if ($foo)
?><?php
while (bar());

while (foo())
if ($bar)
?><?php
PHP,
            ],
            [
                <<<'PHP'
<?php
if ($foo)  /* comment */
?><?php

if ($foo)  // comment
?><?php

if ($foo)  /* comment */
?><?php
else  /* comment */
?><?php

if ($foo)  // comment
?><?php
else  // comment
?>
PHP,
                <<<'PHP'
<?php
if ($foo) /* comment */

?><?php

if ($foo) // comment

?><?php

if ($foo) /* comment */

?><?php

else /* comment */

?><?php

if ($foo) // comment

?><?php

else // comment

?>
PHP,
            ],
            [
                <<<'PHP'
<?php

// comment

if ($foo)
    // comment
?><?php

// comment

if ($foo)
    // comment
?><?php
// comment
else
    // comment
?><?php

// comment

?>
PHP,
                <<<'PHP'
<?php

// comment

if ($foo)

// comment

?><?php

// comment

if ($foo)

// comment

?><?php

// comment

else

// comment

?><?php

// comment

?>
PHP,
            ],
        ];
    }
}
