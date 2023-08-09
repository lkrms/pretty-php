<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

use Lkrms\Pretty\Php\Rule\AlignData;

final class StandardWhitespaceTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider processTokenProvider
     *
     * @param string[] $addRules
     */
    public function testProcessToken(string $expected, string $code, array $addRules = []): void
    {
        $this->assertCodeFormatIs($expected, $code, $addRules);
    }

    /**
     * @return array<string,array{0:string,1:string,2?:string[]}>
     */
    public static function processTokenProvider(): array
    {
        return [
            'indented tags #1' => [
                <<<'PHP'
<html>
<body>
    <?php
        echo $a;
    ?>
</body>
</html>
PHP,
                <<<'PHP'
<html>
<body>
    <?php
echo $a;
    ?>
</body>
</html>
PHP,
            ],
            'indented tags #2' => [
                <<<'PHP'
<?php
if ($a):
    function f() {
        ?>
        <div id="content">
            <?php
            $b = c();
            if (d()) {
                $b = '<span>' . $b . '</span>';
            }
            ?>
            <h1><?php echo $b; ?></h1>
            <?php if (e()): ?>
                <img />
            <?php endif; ?>
        </div>
<?php
    }
endif;

PHP,
                <<<'PHP'
<?php
if ($a):
    function f() {
        ?>
        <div id="content">
            <?php
                $b = c();
                if (d()) {
                    $b = '<span>' . $b . '</span>';
                }
            ?>
            <h1><?php echo $b; ?></h1>
            <?php if (e()): ?>
                <img />
            <?php endif; ?>
        </div>
<?php
    }
endif;
PHP,
            ],
        ] + (PHP_VERSION_ID < 80000 ? [] : [
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
            ],
            "match expressions with 'align-data'" => [
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
                [AlignData::class],
            ],
        ]);
    }
}
