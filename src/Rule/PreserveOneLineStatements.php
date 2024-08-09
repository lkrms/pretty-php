<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;

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
            case self::PROCESS_TOKENS:
                return 95;

            default:
                return null;
        }
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->Statement === $token
                    && !$this->preserveOneLine(
                        $token,
                        $until = $token->pragmaticEndOfExpression(false, false)
                    )
                    && $this->TypeIndex->Attribute[$token->id]) {
                $this->preserveOneLine(
                    $token->skipSiblingsFrom($this->TypeIndex->Attribute),
                    $until
                );
            }
        }
    }
}
