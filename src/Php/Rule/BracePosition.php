<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
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
        if (!$token->isStructuralBrace()) {
            return;
        }

        $next = $token->next();
        if ($token->is('{')) {
            if (($prev = $token->prev())->is(')')) {
                $this->BracketBracePairs[] = [$prev, $token];
            }
            if ($token->isDeclaration() &&
                    (($parent = $token->parent())->isNull() || $parent->is('{')) &&
                    (($prev = ($start = $token->startOfExpression())->prevCode())->isNull() ||
                        $prev->isOneOf(';', '{', '}', T_CLOSE_TAG) ||
                        ($prev->is(']') && $prev->OpenedBy->is(T_ATTRIBUTE))) &&
                    !$start->is(T_USE)) {
                $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
            } else {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
            }
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

    public function beforeRender(): void
    {
        foreach ($this->BracketBracePairs as [$bracket, $brace]) {
            if ($bracket->hasNewlineBefore() && $brace->hasNewlineBefore()) {
                $brace->WhitespaceBefore  |= WhitespaceType::SPACE;
                $brace->WhitespaceMaskPrev = WhitespaceType::SPACE;
            }
        }
    }
}
