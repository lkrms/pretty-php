<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

/**
 * Add newlines after control structures where the body has no enclosing braces
 *
 * Control structures meeting this criteria are also reported to the user via
 * {@see Formatter::reportProblem()}.
 */
final class BreakBeforeControlStructureBody implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 83;
    }

    public function getTokenTypes(): array
    {
        return [
            ...TokenType::HAS_STATEMENT_WITH_OPTIONAL_BRACES,
            ...TokenType::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES,
        ];
    }

    public function processToken(Token $token): void
    {
        // Ignore the second half of `elseif` expressed as `else if`
        if ($token->id === T_IF && ($token->_prevCode->id ?? null) === T_ELSE) {
            return;
        }
        if ($token->id === T_ELSE && $token->_nextCode->id === T_IF) {
            $offset = 3;
        } elseif ($token->is(TokenType::HAS_STATEMENT_WITH_OPTIONAL_BRACES)) {
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
                $body->is([T_COLON, T_SEMICOLON, T_OPEN_BRACE, T_CLOSE_TAG])) {
            return;
        }

        $token->BodyIsUnenclosed = true;

        if ($token->prev()->id !== T_CLOSE_BRACE || !$token->continuesControlStructure()) {
            $token->WhitespaceBefore |= WhitespaceType::LINE;
            $token->WhitespaceMaskPrev |= WhitespaceType::LINE;
            $token->prev()->WhitespaceMaskNext |= WhitespaceType::LINE;
        }

        $body->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
        $body->WhitespaceMaskPrev |= WhitespaceType::LINE;
        $body->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
        $body->prev()->WhitespaceMaskNext |= WhitespaceType::LINE;

        $end = null;
        $continues = false;
        if ($token->id === T_DO) {
            $continues = true;
        } elseif ($token->is([T_IF, T_ELSEIF])) {
            $end = $body->prevSibling()->nextSiblingOf(T_IF, T_ELSEIF, T_ELSE);
            if ($end->id === T_IF) {
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
            $this,
            'Braces not used in %s control structure',
            $token,
            $end,
            $token->getTokenName()
        );
    }
}
