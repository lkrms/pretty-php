<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Rule\AlignAssignments;
use Lkrms\Pretty\Php\Rule\ApplyMagicComma;

final class AddStandardWhitespaceTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider processTokenProvider
     *
     * @param string[] $addRules
     */
    public function testProcessToken(string $code, string $expected, array $addRules = [])
    {
        $this->assertFormatterOutputIs($code, $expected, $addRules, [ApplyMagicComma::class]);
    }

    public static function processTokenProvider()
    {
        return [
            'match expressions' => [
                <<<'PHP'
<?php
$out = match ($in) {0 => 'no items', 1 => "$i item", default => "$in items"};
$out = match ($in) {0, 1 => 'less than 2 items', default => "$in items"};
PHP,
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
            ],
            "match expressions with 'align-assignments'" => [
                <<<'PHP'
<?php
$out = match ($in) {0 => 'no items', 1 => "$i item", default => "$in items"};
$out = match ($in) {0, 1 => 'less than 2 items', default => "$in items"};
PHP,
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
                [AlignAssignments::class]
            ],
        ];
    }

    protected function prepareFormatter(Formatter $formatter): Formatter
    {
        $formatter->MatchesAreLists = false;

        return $formatter;
    }
}
