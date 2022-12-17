<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class BreakBeforeControlStructureBody extends AbstractTokenRule
{
    public function __invoke(Token $token, int $stage): void
    {
        if ($token->isOneOf(...TokenType::HAS_STATEMENT_WITH_OPTIONAL_BRACES)) {
            $offset = 1;
        } elseif ($token->isOneOf(...TokenType::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES)) {
            $offset = 2;
        } else {
            return;
        }

        if (($body = $token->nextSibling($offset))->isOneOf(':', ';', '{')) {
            return;
        }

        $body->WhitespaceBefore           |= WhitespaceType::LINE;
        $body->WhitespaceMaskPrev         |= WhitespaceType::LINE;
        $body->WhitespaceMaskPrev         &= ~WhitespaceType::BLANK;
        $body->prev()->WhitespaceMaskNext |= WhitespaceType::LINE;
        $body->collect($end = $body->endOfStatement())->forEach(fn(Token $t) => $t->Indent++);

        Console::warn(sprintf('Braces not used in %s control structure %s',
            $token->TypeName,
            Convert::pluralRange($token->Line, $end->Line, 'line')));
    }
}
