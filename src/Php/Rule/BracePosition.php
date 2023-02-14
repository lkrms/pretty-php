<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class BracePosition implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @var array<Token[]>
     * @psalm-var array<array{0:Token,1:Token}>
     */
    private $BracketBracePairs = [];

    public function getTokenTypes(): ?array
    {
        return [
            '{',
            '}',
        ];
    }

    public function processToken(Token $token): void
    {
        if (!$token->isStructuralBrace() &&
                !$token->canonical()->prevSibling(2)->is(T_MATCH)) {
            return;
        }

        $next = $token->next();
        if ($token->is('{')) {
            $prev = $token->prev();
            if ($prev->is(')')) {
                $this->BracketBracePairs[] = [$prev, $token];
            }
            $before = WhitespaceType::SPACE;
            // Add a newline before this opening brace if:
            // 1. it's part of a declaration (e.g. `function ... { ... }`
            if ($token->isDeclaration()) {
                // 2. it's not part of a `use` statement
                $start = $token->startOfExpression();
                if (!$start->is(T_USE)) {
                    // 3. the token before the declaration is:
                    //    - `;`
                    //    - `{`
                    //    - `}`
                    //    - a T_CLOSE_TAG statement terminator
                    //    - non-existent (no code precedes the declaration), or
                    //    - the last token of an attribute
                    $prevCode = $start->prevCode();
                    if ($prevCode->isOneOf(';', '{', '}', T_CLOSE_TAG, TokenType::T_NULL) ||
                            ($prevCode->is(']') && $prevCode->OpenedBy->is(T_ATTRIBUTE))) {
                        $before |= WhitespaceType::LINE;
                    }
                }
            }
            $token->WhitespaceBefore   |= $before;
            $token->WhitespaceAfter    |= WhitespaceType::LINE | WhitespaceType::SPACE;
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            if ($next->is('}')) {
                $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
            }

            return;
        }

        $token->WhitespaceBefore   |= WhitespaceType::LINE | WhitespaceType::SPACE;
        $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;

        if ($next->isOneOf(T_ELSE, T_ELSEIF, T_CATCH, T_FINALLY) ||
                ($next->is(T_WHILE) && $next->nextSibling(2)->isOneOf(';', T_CLOSE_TAG))) {
            $token->WhitespaceAfter    |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;

            return;
        }

        $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
    }

    public function beforeRender(array $tokens): void
    {
        foreach ($this->BracketBracePairs as [$bracket, $brace]) {
            if ($bracket->hasNewlineBefore() && $brace->hasNewlineBefore()) {
                $brace->WhitespaceBefore  |= WhitespaceType::SPACE;
                $brace->WhitespaceMaskPrev = WhitespaceType::SPACE;
            }
        }
    }
}
