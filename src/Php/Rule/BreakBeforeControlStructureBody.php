<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class BreakBeforeControlStructureBody implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if ($token->isOneOf(...TokenType::HAS_STATEMENT_WITH_OPTIONAL_BRACES)) {
            $offset = 1;
        } elseif ($token->isOneOf(...TokenType::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES)) {
            $offset = 2;
        } else {
            return;
        }

        if (($body = $token->nextSibling($offset))->isOneOf(':', ';', '{', T_CLOSE_TAG, TokenType::T_NULL)) {
            return;
        }

        $body->WhitespaceBefore           |= WhitespaceType::LINE;
        $body->WhitespaceMaskPrev         |= WhitespaceType::LINE;
        $body->WhitespaceMaskPrev         &= ~WhitespaceType::BLANK;
        $body->prev()->WhitespaceMaskNext |= WhitespaceType::LINE;
        $body->collect($end = $body->endOfStatement())->forEach(fn(Token $t) => $t->Indent++);
        $this->Formatter->reportProblem('Braces not used in %s control structure', $token, $end, $token->TypeName);
    }
}
