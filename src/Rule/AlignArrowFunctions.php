<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\TokenRule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Align arrow function expressions with their definitions
 */
final class AlignArrowFunctions implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKEN:
                return 380;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
    {
        return [T_FN];
    }

    public function processToken(Token $token): void
    {
        $arrow = $token->nextSiblingOf(T_DOUBLE_ARROW);
        $body = $this->Formatter->NewlineBeforeFnDoubleArrows
            ? $arrow
            : $arrow->_nextCode;

        if (!$body->hasNewlineBefore()) {
            return;
        }

        // If the arrow function's arguments break over multiple lines, align
        // with the start of the previous line
        $alignWith = $token->collect($body->_prev)
                           ->reverse()
                           ->find(fn(Token $t) =>
                                      $t->IsCode && $t->hasNewlineBefore() ||
                                          $t === $token);

        $body->AlignedWith = $alignWith;
        $this->Formatter->registerCallback(
            $this,
            $body,
            fn() => $this->alignBody($body, $alignWith, $token->EndStatement),
            710
        );
    }

    private function alignBody(Token $body, Token $alignWith, Token $until): void
    {
        $delta = $body->getIndentDelta($alignWith);
        $delta->LinePadding +=
            $alignWith->alignmentOffset(false) + $this->Formatter->TabSize;

        foreach ($body->collect($until) as $token) {
            $delta->apply($token);
        }
    }
}
