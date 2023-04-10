<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Add newlines after control structures where the body has no enclosing braces
 *
 * Control structures meeting this criteria are also reported to the user via
 * {@see \Lkrms\Pretty\Php\Formatter::reportProblem()}.
 */
final class BreakBeforeControlStructureBody implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 83;
    }

    public function getTokenTypes(): ?array
    {
        return [
            ...TokenType::HAS_STATEMENT_WITH_OPTIONAL_BRACES,
            ...TokenType::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES,
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->is(TokenType::HAS_STATEMENT_WITH_OPTIONAL_BRACES)) {
            $offset = 1;
        } else {
            $offset = 2;
        }

        /**
         * For reference, the following code prints "11" because the T_CLOSE_TAG
         * terminates the `while` structure:
         *
         * ```php
         * <?php
         * $i = 0;
         * while ($i++ < 10)
         * ?><?php
         * echo $i;
         * ```
         */
        $body = $token->nextSibling($offset);
        if ($body->IsNull ||
                $body->is([T[':'], T[';'], T['{'], T_CLOSE_TAG])) {
            return;
        }

        $token->WhitespaceBefore |= WhitespaceType::LINE;
        $token->WhitespaceMaskPrev |= WhitespaceType::LINE;
        $token->prev()->WhitespaceMaskNext |= WhitespaceType::LINE;

        $body->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
        $body->WhitespaceMaskPrev |= WhitespaceType::LINE;
        $body->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
        $body->prev()->WhitespaceMaskNext |= WhitespaceType::LINE;

        $end = null;
        $continues = false;
        if ($token->is(T_DO)) {
            $continues = true;
        } elseif ($token->is([T_IF, T_ELSEIF])) {
            $end = $token->nextSiblingOf(T_IF, T_ELSEIF, T_ELSE);
            if ($end->is(T_IF)) {
                $end = $body->EndStatement;
            } elseif (!$end->IsNull) {
                $end = $end->prevCode();
                $continues = true;
            }
        }

        if (!$end || $end->IsNull || $end->Index > $body->EndStatement->Index) {
            $end = $body->pragmaticEndOfExpression(true)->withTerminator();
        }

        $body->collect($end)
             // Use PreIndent because AddIndentation clobbers Indent
             ->forEach(fn(Token $t) => $t->PreIndent++);

        $end->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
        $end->WhitespaceMaskNext |= WhitespaceType::LINE;
        if ($continues) {
            $end->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            $end->next()->WhitespaceMaskPrev |= WhitespaceType::LINE;
        }

        $this->Formatter->reportProblem(
            'Braces not used in %s control structure',
            $token,
            $end,
            $token->getTokenName()
        );
    }
}
