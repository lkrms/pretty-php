<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Suppress newlines in statements and control structures that start and end on
 * the same line, including individual case statements
 *
 * Examples:
 *
 * ```php
 * // Short anonymous functions
 * $callback = function ($value) { $result = doSomethingWith($value); return $result; };
 *
 * // Case statements
 * switch ($value) {
 *     case 1: $result = doSomething(); break;
 *     case 2: $result = doSomethingElse(); break;
 *     default: $result = doDefaultThing(); break;
 * }
 * ```
 */
final class PreserveOneLineStatements implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKEN:
                return 95;

            default:
                return null;
        }
    }

    public function processToken(Token $token): void
    {
        if ($token->Statement === $token
                && !$this->preserveOneLine(
                    $token,
                    $until = $token->pragmaticEndOfExpression(false, false)
                )
                && $token->is([\T_ATTRIBUTE, \T_ATTRIBUTE_COMMENT])) {
            $this->preserveOneLine(
                $token->skipSiblingsOf(\T_ATTRIBUTE, \T_ATTRIBUTE_COMMENT),
                $until
            );
        }
    }
}
