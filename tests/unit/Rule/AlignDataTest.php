<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignData;
use Lkrms\PrettyPHP\Rule\PreserveOneLineStatements;
use Lkrms\PrettyPHP\Tests\TestCase;

final class AlignDataTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code, [AlignData::class, PreserveOneLineStatements::class]);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function outputProvider(): array
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
