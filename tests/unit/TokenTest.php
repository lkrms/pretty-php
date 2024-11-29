<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Lkrms\PrettyPHP\Catalog\TokenSubId;
use Lkrms\PrettyPHP\Formatter;
use Salient\Utility\Reflect;

final class TokenTest extends TestCase
{
    /**
     * @requires PHP >= 8.1
     */
    public function testColonSubIds(): void
    {
        $code = <<<'PHP'
<?php
enum Actions: string
{
    case Run = 'run';
}

function getIndex(int $size, bool $fromZero = true): array
{
    return array_fill(start_index: $fromZero ? 0 : 1, count: $size, value: true);
}

start:
switch ($argv[1] ?? null):
    case Actions::Run->value:
        break;
    default:
        exit(1);
endswitch;

PHP;

        $formatter = (new Formatter())->withDebug();
        $formatter->format($code, \PHP_EOL, null, null, true);

        $actual = [];
        foreach ($formatter->getTokens() ?? [] as $token) {
            if ($token->id === \T_COLON) {
                $actual[] = Reflect::getConstantName(TokenSubId::class, $token->getSubId());
            }
        }

        $this->assertSame([
            'COLON_BACKED_ENUM_TYPE_DELIMITER',
            'COLON_RETURN_TYPE_DELIMITER',
            'COLON_NAMED_ARGUMENT_DELIMITER',
            'COLON_TERNARY_OPERATOR',
            'COLON_NAMED_ARGUMENT_DELIMITER',
            'COLON_NAMED_ARGUMENT_DELIMITER',
            'COLON_LABEL_DELIMITER',
            'COLON_ALT_SYNTAX_DELIMITER',
            'COLON_SWITCH_CASE_DELIMITER',
            'COLON_SWITCH_CASE_DELIMITER',
        ], $actual);
        $this->assertCount(7, array_unique($actual));
    }
}
