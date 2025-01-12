<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignData;
use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;

final class StandardSpacingTest extends TestCase
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
        $formatterB = Formatter::build();
        $formatter = $formatterB->build();

        yield from [
            'indented tags #1' => [
                <<<'PHP'
<div>
    <?php
    echo $a;
    ?>
</div>
PHP,
                <<<'PHP'
<div>
    <?php
echo $a;
?>
</div>
PHP,
                $formatter,
            ],
            'indented tags #2' => [
                <<<'PHP'
<div>
  <?php
echo $a;
?>
</div>
PHP,
                <<<'PHP'
<div>
  <?php
echo $a;
?>
</div>
PHP,
                $formatter,
            ],
            'indented tags #3' => [
                <<<'PHP'
<?php
if ($foo):
    function foo()
    {
        ?>
        <div id="content">
            <?php
            $bar = bar();
            if (baz()) {
                $bar = "<span>{$bar}</span>";
            }
            ?>
            <h1><?php echo $bar; ?></h1>
            <?php if (qux()): ?>
            <img />
            <?php endif; ?>
        </div>
    <?php
    }
endif;

PHP,
                <<<'PHP'
<?php
if ($foo):
    function foo() {
?>
        <div id="content">
            <?php
        $bar = bar();
        if (baz()) {
            $bar = "<span>{$bar}</span>";
        }
            ?>
            <h1><?php echo $bar; ?></h1>
            <?php if (qux()): ?>
            <img />
            <?php endif; ?>
        </div>
    <?php
    }
endif;
PHP,
                $formatter,
            ],
            'indented tags #4' => [
                <<<'PHP'
<?php
function foo()
{
    if ($bar) {
        // do stuff
        ?>
    <?php } else { ?>
        <!-- output stuff -->
        <?php
    }
}
?>
PHP,
                <<<'PHP'
<?php
function foo()
{
    if ($bar) {
        // do stuff
?>
    <?php } else { ?>
        <!-- output stuff -->
        <?php
    }
}
?>
PHP,
                $formatter,
            ],
            'indented tags #5' => [
                <<<'PHP'
<?php
if ($foo) {
?>
<span>
    <select>
    <?= $bar ?>
    </select>
</span>
<?php
}
?>
PHP,
                <<<'PHP'
<?php
if ($foo) {
?>
<span>
    <select>
    <?= $bar ?>
    </select>
</span>
<?php
}
?>
PHP,
                $formatter,
            ],
            'unindented tags' => [
                <<<'PHP'
<?php
if (str_contains($_SERVER['HTTP_USER_AGENT'], 'Firefox')) {
?>
<h3>str_contains() returned true</h3>
<p>You are using Firefox</p>
<?php
} else {
?>
<h3>str_contains() returned false</h3>
<p>You are not using Firefox</p>
<?php
}
?>
PHP,
                <<<'PHP'
<?php
if (str_contains($_SERVER['HTTP_USER_AGENT'], 'Firefox')) {
?>
<h3>str_contains() returned true</h3>
<p>You are using Firefox</p>
<?php
} else {
?>
<h3>str_contains() returned false</h3>
<p>You are not using Firefox</p>
<?php
}
?>
PHP,
                $formatter,
            ],
        ];

        if (\PHP_VERSION_ID < 80000) {
            return;
        }

        yield from [
            'match expressions' => [
                <<<'PHP'
<?php
$out = match ($in) {
    0 => 'no items',
    1 => "$i item",
    default => "$in items"
};
$out = match ($in) {
    0, 1 => 'less than 2 items',
    default => "$in items"
};

PHP,
                <<<'PHP'
<?php
$out = match ($in) {0 => 'no items', 1 => "$i item", default => "$in items"};
$out = match ($in) {0, 1 => 'less than 2 items', default => "$in items"};
PHP,
                $formatter,
            ],
            'match expressions with AlignData' => [
                <<<'PHP'
<?php
$out = match ($in) {
    0       => 'no items',
    1       => "$i item",
    default => "$in items"
};
$out = match ($in) {
    0, 1    => 'less than 2 items',
    default => "$in items"
};

PHP,
                <<<'PHP'
<?php
$out = match ($in) {0 => 'no items', 1 => "$i item", default => "$in items"};
$out = match ($in) {0, 1 => 'less than 2 items', default => "$in items"};
PHP,
                $formatterB->enable([AlignData::class]),
            ],
            'promoted constructor parameters #1' => [
                <<<'PHP'
<?php
class Point
{
    public function __construct(
        protected int $x,
        protected int $y = 0
    ) {}
}

PHP,
                <<<'PHP'
<?php
class Point {
    public function __construct(protected int $x, protected int $y = 0) {}
}
PHP,
                $formatter,
            ],
            'promoted constructor parameters #2' => [
                <<<'PHP'
<?php
class Foo
{
    public function __construct(
        private $bar
    ) {}
}

class Bar
{
    public function __construct(
        private $bar
    ) {}
}

PHP,
                <<<'PHP'
<?php
class Foo {
    public function __construct(private $bar) {}
}
class Bar {
    public function __construct(private $bar) {}
}
PHP,
                $formatter,
            ],
        ];
    }
}
