<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

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
 *
 */
final class PreserveOneLineStatements implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if ($token->is([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO]) && $token->CloseTag) {
            $this->maybePreserveOneLine($token, $token->CloseTag);
        } elseif ($token->Statement && $token->Statement === $token) {
            // We can't use EndStatement here because the `case <value>:`
            // statement ends at `:`
            $this->maybePreserveOneLine($token, $token->pragmaticEndOfExpression());
        }
    }

    private function maybePreserveOneLine(Token $start, Token $end): void
    {
        if ($start->line === $end->line && $start->Index < $end->Index) {
            $mask = ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
            $start->next()
                  ->collect($end->prev())
                  ->forEach(
                      function (Token $t) use ($mask) {
                          $t->WhitespaceMaskPrev &= $mask;
                          $t->WhitespaceMaskNext &= $mask;
                      }
                  );
            $start->WhitespaceMaskNext &= $mask;
            $end->WhitespaceMaskPrev   &= $mask;
        }
    }
}
