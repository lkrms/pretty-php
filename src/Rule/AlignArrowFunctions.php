<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;

/**
 * Align arrow function expressions with their definitions
 *
 */
final class AlignArrowFunctions implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 380;
    }

    public function getTokenTypes(): array
    {
        return [T_FN];
    }

    public function processToken(Token $token): void
    {
        $body = $token->nextSiblingOf(T_DOUBLE_ARROW)
                      ->nextCode();
        if (!$body->hasNewlineBefore()) {
            return;
        }

        // If the arrow function's arguments break over multiple lines, align
        // with the start of the previous line
        $alignWith = $token->collect($body->prev())
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
        $diff = $body->getIndentDiff($alignWith);
        $diff['LinePadding'] +=
            $alignWith->alignmentOffset(false) + $this->Formatter->TabSize;
        $body->collect($until)
             ->forEach(fn(Token $t) => $t->applyIndentDiff($diff));
    }
}
