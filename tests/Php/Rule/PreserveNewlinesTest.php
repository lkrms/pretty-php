<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

final class PreserveNewlinesTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider processTokenProvider
     */
    public function testProcessToken(string $code, string $expected)
    {
        $this->assertFormatterOutputIs($code, $expected);
    }

    public static function processTokenProvider()
    {
        return [
            'logical operator after bracket' => [
                <<<'PHP'
<?php
return a($b) && a($c)
    && strcmp((string) $b, (string) $c) === 0;
PHP,
                <<<'PHP'
<?php
return a($b) && a($c) &&
    strcmp((string) $b, (string) $c) === 0;

PHP,
            ],
        ];
    }
}
