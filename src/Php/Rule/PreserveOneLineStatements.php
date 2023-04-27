<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;
use Lkrms\Utility\Test;

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

    public function getPriority(string $method): ?int
    {
        return 95;
    }

    public function processToken(Token $token): void
    {
        // We can't use EndStatement here because the `case <value>:`
        // statement ends at `:`
        if ($token->Statement &&
                $token->Statement === $token &&
                $this->maybePreserveOneLine(
                    $token,
                    $token->pragmaticEndOfExpression(false, false)
                )) {
            return;
        }

        if (!$token->is([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO]) ||
                !$token->CloseTag ||
                $this->maybePreserveOneLine(
                    $token,
                    $token->CloseTag
                )) {
            return;
        }

        $firstCode = $token->nextCode();
        if ($firstCode->IsNull ||
                !Test::isBetween(
                    $firstCode->Index,
                    $token->Index,
                    $token->CloseTag->Index
                )) {
            return;
        }
        $lastCode = $token->CloseTag->prevCode();
        if ($firstCode->line === $token->line &&
                $lastCode->line === $token->CloseTag->line) {
            $this->maybePreserveOneLine($token, $firstCode, true);
            $this->maybePreserveOneLine($lastCode, $token->CloseTag, true);
        }
    }

    private function maybePreserveOneLine(Token $start, Token $end, bool $skipCheck = false): bool
    {
        if (!$skipCheck && $start->line !== $end->line) {
            return false;
        }
        $start->collect($end)
              ->maskInnerWhitespace(~WhitespaceType::BLANK & ~WhitespaceType::LINE);

        return true;
    }
}
