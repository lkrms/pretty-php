<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignData;
use Lkrms\PrettyPHP\Rule\PreserveOneLineStatements;

final class AlignDataTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider processBlockProvider
     */
    public function testProcessBlock(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code, [AlignData::class, PreserveOneLineStatements::class]);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function processBlockProvider(): array
    {
        return [
            'one-line switch blocks' => [
                <<<'PHP'
<?php
switch ($operator) {
    default:
    case '=':
    case '==':  return $retrieved == $value;
    case '!=':
    case '<>':  return $retrieved != $value;
    case '<':   return $retrieved < $value;
    case '>':   return $retrieved > $value;
    case '<=':  return $retrieved <= $value;
    case '>=':  return $retrieved >= $value;
    case '===': return $retrieved === $value;
    case '!==': return $retrieved !== $value;
    case '<=>': return $retrieved <=> $value;
}

PHP,
                <<<'PHP'
<?php
switch ($operator) {
default:
case '=':
case '==': return $retrieved == $value;
case '!=':
case '<>': return $retrieved != $value;
case '<': return $retrieved < $value;
case '>': return $retrieved > $value;
case '<=': return $retrieved <= $value;
case '>=': return $retrieved >= $value;
case '===': return $retrieved === $value;
case '!==': return $retrieved !== $value;
case '<=>': return $retrieved <=> $value;
}
PHP,
            ],
        ];
    }
}
