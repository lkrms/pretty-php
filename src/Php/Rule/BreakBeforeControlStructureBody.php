<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

/**
 * Add newlines after control structures where the body has no enclosing braces
 *
 * Control structures meeting this criteria are also reported to the user via
 * {@see \Lkrms\Pretty\Php\Formatter::reportProblem()}.
 */
final class BreakBeforeControlStructureBody implements TokenRule
{
    use TokenRuleTrait;

    public function getTokenTypes(): ?array
    {
        return [
            ...TokenType::HAS_STATEMENT_WITH_OPTIONAL_BRACES,
            ...TokenType::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES,
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->isOneOf(...TokenType::HAS_STATEMENT_WITH_OPTIONAL_BRACES)) {
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
        if ($body->isNull() ||
                $body->isOneOf(':', ';', '{', T_CLOSE_TAG)) {
            return;
        }

        $body->WhitespaceBefore           |= WhitespaceType::LINE | WhitespaceType::SPACE;
        $body->WhitespaceMaskPrev         |= WhitespaceType::LINE;
        $body->WhitespaceMaskPrev         &= ~WhitespaceType::BLANK;
        $body->prev()->WhitespaceMaskNext |= WhitespaceType::LINE;

        $body->collect($end = $body->endOfStatement())
             // Use PreIndent because AddIndentation clobbers Indent
             ->forEach(fn(Token $t) => $t->PreIndent++);

        $this->Formatter->reportProblem('Braces not used in %s control structure',
                                        $token,
                                        $end,
                                        $token->TypeName);
    }
}
