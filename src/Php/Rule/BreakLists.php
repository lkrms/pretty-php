<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\ListRuleTrait;
use Lkrms\Pretty\Php\Contract\ListRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Normalise multi-line lists
 *
 * Specifically:
 * - If an interface list (`extends` or `implements`, depending on context)
 *   breaks over multiple lines, place every item on its own line.
 * - If one or more parameters in an argument list break over multiple lines to
 *   accommodate a T_ATTRIBUTE, place every parameter on its own line, and add
 *   blank lines before and after annotated parameters to improve readability.
 *
 */
final class BreakLists implements ListRule
{
    use ListRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 98;
    }

    public function processList(Token $owner, TokenCollection $items): void
    {
        if (!$owner->ClosedBy) {
            if ($items->find(fn(Token $token) => $token->hasNewlineBefore())) {
                $items->addWhitespaceBefore(WhitespaceType::LINE, true);
            }
            return;
        }

        if ($owner->id === T['('] &&
                ($owner->isDeclaration(T_FUNCTION) || $owner->prevCode()->id === T_FN) &&
                $items->find(fn(Token $token) => $this->hasAttribute($token))) {
            $items->forEach(
                function (Token $token, ?Token $next, ?Token $prev) {
                    if (!$this->hasAttribute($token)) {
                        $token->WhitespaceBefore |= WhitespaceType::LINE;
                        $token->WhitespaceMaskPrev |= WhitespaceType::LINE;
                        $token->prev()->WhitespaceMaskNext |= WhitespaceType::LINE;

                        return;
                    }
                    if ($prev) {
                        $token->WhitespaceBefore |= WhitespaceType::BLANK;
                        $token->WhitespaceMaskPrev |= WhitespaceType::BLANK;
                        $token->prev()->WhitespaceMaskNext |= WhitespaceType::BLANK;
                    }
                    if ($next) {
                        $next->WhitespaceBefore |= WhitespaceType::BLANK;
                        $next->WhitespaceMaskPrev |= WhitespaceType::BLANK;
                        $next->prev()->WhitespaceMaskNext |= WhitespaceType::BLANK;
                    }
                }
            );
        }
    }

    private function hasAttribute(Token $token): bool
    {
        return $token->id === T_ATTRIBUTE &&
            $token->hasNewlineBefore() &&
            $token->ClosedBy->hasNewlineAfter();
    }
}
