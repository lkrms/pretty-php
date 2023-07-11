<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

use Lkrms\Pretty\Php\Rule\AlignAssignments;
use Lkrms\Pretty\Php\Rule\PreserveOneLineStatements;

final class AlignAssignmentsTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider processBlockProvider
     */
    public function testProcessBlock(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code, [AlignAssignments::class, PreserveOneLineStatements::class]);
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
