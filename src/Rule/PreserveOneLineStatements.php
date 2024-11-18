<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Concern\StatementRuleTrait;
use Lkrms\PrettyPHP\Contract\StatementRule;

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
final class PreserveOneLineStatements implements StatementRule
{
    use StatementRuleTrait;

    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_STATEMENTS => 95,
        ][$method] ?? null;
    }

    public function processStatements(array $statements): void
    {
        foreach ($statements as $token) {
            if (
                !$this->preserveOneLine(
                    $token,
                    $until = $token->pragmaticEndOfExpression(false, false)
                )
                && $this->Idx->Attribute[$token->id]
            ) {
                $this->preserveOneLine(
                    $token->skipNextSiblingsFrom($this->Idx->Attribute),
                    $until
                );
            }
        }
    }
}
