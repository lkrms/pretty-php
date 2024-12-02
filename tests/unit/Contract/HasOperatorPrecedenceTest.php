<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Contract;

use Lkrms\PrettyPHP\Contract\HasOperatorPrecedence;
use Lkrms\PrettyPHP\Contract\HasTokenIndex;
use Lkrms\PrettyPHP\Tests\TestCase;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Reflect;

final class HasOperatorPrecedenceTest extends TestCase implements HasOperatorPrecedence, HasTokenIndex
{
    private const LEFT_ASSOCIATIVE = 8;
    private const RIGHT_ASSOCIATIVE = 16;

    /**
     * [ [ Precedence, [ token ID, ... ], flags ], ... ]
     *
     * @var array<array{int,int[],2?:int}>
     */
    private const OPERATOR_PRECEDENCE_DATA = [
        // clone, new
        [0, [\T_CLONE, \T_NEW]],
        // **
        [1, [\T_POW], self::RIGHT_ASSOCIATIVE],
        // +, -
        [2, [\T_PLUS, \T_MINUS], self::UNARY],
        // ++, --, ~, (int), (float), (string), (array), (object), (bool), @
        [2, [\T_INC, \T_DEC, \T_NOT, \T_INT_CAST, \T_DOUBLE_CAST, \T_STRING_CAST, \T_ARRAY_CAST, \T_OBJECT_CAST, \T_BOOL_CAST, \T_UNSET_CAST, \T_AT]],
        // instanceof
        [3, [\T_INSTANCEOF], self::LEFT_ASSOCIATIVE],
        // !
        [4, [\T_LOGICAL_NOT]],
        // *, /, %
        [5, [\T_MUL, \T_DIV, \T_MOD], self::LEFT_ASSOCIATIVE],
        // +, -
        [6, [\T_PLUS, \T_MINUS], self::LEFT_ASSOCIATIVE | self::BINARY],
        // <<, >>
        [7, [\T_SL, \T_SR], self::LEFT_ASSOCIATIVE],
        // .
        [8, [\T_CONCAT], self::LEFT_ASSOCIATIVE],
        // <, <=, >, >=
        [9, [\T_SMALLER, \T_IS_SMALLER_OR_EQUAL, \T_GREATER, \T_IS_GREATER_OR_EQUAL]],
        // ==, !=, ===, !==, <>, <=>
        [10, [\T_IS_EQUAL, \T_IS_NOT_EQUAL, \T_IS_IDENTICAL, \T_IS_NOT_IDENTICAL, \T_SPACESHIP]],
        // &
        [11, [\T_AND, \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG], self::LEFT_ASSOCIATIVE],
        // ^
        [12, [\T_XOR], self::LEFT_ASSOCIATIVE],
        // |
        [13, [\T_OR], self::LEFT_ASSOCIATIVE],
        // &&
        [14, [\T_BOOLEAN_AND], self::LEFT_ASSOCIATIVE],
        // ||
        [15, [\T_BOOLEAN_OR], self::LEFT_ASSOCIATIVE],
        // ??
        [16, [\T_COALESCE], self::RIGHT_ASSOCIATIVE],
        // ?, :
        [17, [\T_QUESTION, \T_COLON], self::TERNARY],
        // =, +=, -=, *=, **=, /=, .=, %=, &=, |=, ^=, <<=, >>=, ??=
        [18, [\T_EQUAL, \T_PLUS_EQUAL, \T_MINUS_EQUAL, \T_MUL_EQUAL, \T_POW_EQUAL, \T_DIV_EQUAL, \T_CONCAT_EQUAL, \T_MOD_EQUAL, \T_AND_EQUAL, \T_OR_EQUAL, \T_XOR_EQUAL, \T_SL_EQUAL, \T_SR_EQUAL, \T_COALESCE_EQUAL], self::RIGHT_ASSOCIATIVE],
        // yield from
        [19, [\T_YIELD_FROM]],
        // yield
        [20, [\T_YIELD]],
        // print
        [21, [\T_PRINT]],
        // and
        [22, [\T_LOGICAL_AND], self::LEFT_ASSOCIATIVE],
        // xor
        [23, [\T_LOGICAL_XOR], self::LEFT_ASSOCIATIVE],
        // or
        [24, [\T_LOGICAL_OR], self::LEFT_ASSOCIATIVE],
    ];

    public function testOperatorPrecedence(): void
    {
        $data = self::OPERATOR_PRECEDENCE_DATA;

        $data[] = [99, [\T_DOUBLE_ARROW]];

        /** @var array<int[]> */
        $ids = Arr::pluck($data, '1');
        $ids = array_diff(
            array_keys(self::HAS_EXPRESSION_WITH_OPTIONAL_PARENTHESES),
            array_merge(...$ids),
        );
        if ($ids) {
            $data[] = [99, $ids];
        }

        foreach ($data as $entry) {
            $flags = $entry[2] ?? 0;
            $leftAssoc = (bool) ($flags & self::LEFT_ASSOCIATIVE);
            $rightAssoc = (bool) ($flags & self::RIGHT_ASSOCIATIVE);
            $arity = $flags & ~(self::LEFT_ASSOCIATIVE | self::RIGHT_ASSOCIATIVE);
            if ($arity) {
                $this->assertTrue(
                    in_array($arity, [self::UNARY, self::BINARY, self::TERNARY], true),
                    sprintf('Invalid flags: %s', Get::code($entry)),
                );
                $arityCode = 'self::' . Reflect::getConstantName(HasOperatorPrecedence::class, $arity);
                $constants[$arityCode] = $arityCode;
            } else {
                $arityCode = 0;
            }
            foreach ($entry[1] as $id) {
                $idCode = '\\' . self::getTokenName($id);
                $precedence[$id][] = [$arity, $entry[0], $leftAssoc, $rightAssoc];
                $precedenceCode[$idCode][] = [$arityCode, $entry[0], $leftAssoc, $rightAssoc];
                $constants[$idCode] = $idCode;
            }
        }

        $this->assertSame(
            $precedence,
            self::OPERATOR_PRECEDENCE,
            sprintf(
                'If precedence data changed, replace %s::OPERATOR_PRECEDENCE with: %s',
                HasOperatorPrecedence::class,
                Get::code($precedenceCode, ', ', ' => ', null, '    ', [], $constants),
            ),
        );

        $operators = array_keys(self::OPERATOR_PRECEDENCE);
        $idxOperators = array_keys([
            \T_AT => true,
            \T_CONCAT => true,
            \T_DOUBLE_ARROW => true,
            \T_INC => true,
            \T_DEC => true,
            \T_INSTANCEOF => true,
            \T_NEW => true,
        ]
            + self::OPERATOR_ARITHMETIC
            + self::OPERATOR_ASSIGNMENT
            + self::OPERATOR_BITWISE
            + self::OPERATOR_COMPARISON
            + self::OPERATOR_LOGICAL
            + self::OPERATOR_TERNARY
            + self::CAST
            + self::HAS_EXPRESSION_WITH_OPTIONAL_PARENTHESES);

        $this->assertSame([], self::getTokenNames(array_diff($idxOperators, $operators)));
        $this->assertSame([], self::getTokenNames(array_diff($operators, $idxOperators)));
    }
}
