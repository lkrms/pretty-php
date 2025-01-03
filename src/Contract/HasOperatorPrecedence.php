<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

/**
 * @internal
 */
interface HasOperatorPrecedence
{
    public const UNARY = 1;
    public const BINARY = 2;
    public const TERNARY = 4;

    /**
     * [ Token ID => [ [ arity, precedence, left associative?, right associative? ], ... ], ... ]
     *
     * @var array<int,array<array{int,int,bool,bool}>>
     */
    public const OPERATOR_PRECEDENCE = [
        \T_CLONE => [[0, 1, false, false]],
        \T_NEW => [[0, 1, false, false]],
        \T_POW => [[0, 2, false, true]],
        \T_PLUS => [[self::UNARY, 3, false, false], [self::BINARY, 7, true, false]],
        \T_MINUS => [[self::UNARY, 3, false, false], [self::BINARY, 7, true, false]],
        \T_INC => [[0, 3, false, false]],
        \T_DEC => [[0, 3, false, false]],
        \T_NOT => [[0, 3, false, false]],
        \T_INT_CAST => [[0, 3, false, false]],
        \T_DOUBLE_CAST => [[0, 3, false, false]],
        \T_STRING_CAST => [[0, 3, false, false]],
        \T_ARRAY_CAST => [[0, 3, false, false]],
        \T_OBJECT_CAST => [[0, 3, false, false]],
        \T_BOOL_CAST => [[0, 3, false, false]],
        \T_UNSET_CAST => [[0, 3, false, false]],
        \T_AT => [[0, 3, false, false]],
        \T_INSTANCEOF => [[0, 4, true, false]],
        \T_LOGICAL_NOT => [[0, 5, false, false]],
        \T_MUL => [[0, 6, true, false]],
        \T_DIV => [[0, 6, true, false]],
        \T_MOD => [[0, 6, true, false]],
        \T_SL => [[0, 8, true, false]],
        \T_SR => [[0, 8, true, false]],
        \T_CONCAT => [[0, 9, true, false]],
        \T_SMALLER => [[0, 10, false, false]],
        \T_IS_SMALLER_OR_EQUAL => [[0, 10, false, false]],
        \T_GREATER => [[0, 10, false, false]],
        \T_IS_GREATER_OR_EQUAL => [[0, 10, false, false]],
        \T_IS_EQUAL => [[0, 11, false, false]],
        \T_IS_NOT_EQUAL => [[0, 11, false, false]],
        \T_IS_IDENTICAL => [[0, 11, false, false]],
        \T_IS_NOT_IDENTICAL => [[0, 11, false, false]],
        \T_SPACESHIP => [[0, 11, false, false]],
        \T_AND => [[0, 12, true, false]],
        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG => [[0, 12, true, false]],
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG => [[0, 12, true, false]],
        \T_XOR => [[0, 13, true, false]],
        \T_OR => [[0, 14, true, false]],
        \T_BOOLEAN_AND => [[0, 15, true, false]],
        \T_BOOLEAN_OR => [[0, 16, true, false]],
        \T_COALESCE => [[0, 17, false, true]],
        \T_QUESTION => [[self::TERNARY, 18, false, false]],
        \T_COLON => [[self::TERNARY, 18, false, false]],
        \T_EQUAL => [[0, 19, false, true]],
        \T_PLUS_EQUAL => [[0, 19, false, true]],
        \T_MINUS_EQUAL => [[0, 19, false, true]],
        \T_MUL_EQUAL => [[0, 19, false, true]],
        \T_POW_EQUAL => [[0, 19, false, true]],
        \T_DIV_EQUAL => [[0, 19, false, true]],
        \T_CONCAT_EQUAL => [[0, 19, false, true]],
        \T_MOD_EQUAL => [[0, 19, false, true]],
        \T_AND_EQUAL => [[0, 19, false, true]],
        \T_OR_EQUAL => [[0, 19, false, true]],
        \T_XOR_EQUAL => [[0, 19, false, true]],
        \T_SL_EQUAL => [[0, 19, false, true]],
        \T_SR_EQUAL => [[0, 19, false, true]],
        \T_COALESCE_EQUAL => [[0, 19, false, true]],
        \T_YIELD_FROM => [[0, 20, false, false]],
        \T_YIELD => [[0, 21, false, false]],
        \T_PRINT => [[0, 22, false, false]],
        \T_LOGICAL_AND => [[0, 23, true, false]],
        \T_LOGICAL_XOR => [[0, 24, true, false]],
        \T_LOGICAL_OR => [[0, 25, true, false]],
        \T_DOUBLE_ARROW => [[0, 99, false, false]],
        \T_BREAK => [[0, 99, false, false]],
        \T_CASE => [[0, 99, false, false]],
        \T_CONTINUE => [[0, 99, false, false]],
        \T_ECHO => [[0, 99, false, false]],
        \T_EXIT => [[0, 99, false, false]],
        \T_INCLUDE => [[0, 99, false, false]],
        \T_INCLUDE_ONCE => [[0, 99, false, false]],
        \T_REQUIRE => [[0, 99, false, false]],
        \T_REQUIRE_ONCE => [[0, 99, false, false]],
        \T_RETURN => [[0, 99, false, false]],
        \T_THROW => [[0, 99, false, false]],
    ];
}
