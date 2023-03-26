<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

/**
 * Align arrow functions with their bodies
 *
 */
final class AlignArrowFunctions implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 380;
    }

    public function getTokenTypes(): ?array
    {
        return [T_FN];
    }

    public function processToken(Token $token): void
    {
        $body = $token->nextSiblingOf(...TokenType::OPERATOR_DOUBLE_ARROW)
                      ->nextCode();
        if (!$body->hasNewlineBefore()) {
            return;
        }

        $body->AlignedWith = $token;
        $this->Formatter->registerCallback(
            $this,
            $body,
            fn() => $this->alignBody($body, $token),
            710
        );
    }

    private function alignBody(Token $body, Token $alignWith): void
    {
        $delta = $alignWith->alignmentOffset() - mb_strlen($alignWith->text) + $this->Formatter->TabSize;
        $until = $alignWith->EndStatement;

        $body->collect($until)
             ->forEach(fn(Token $t) =>
                           $t->LinePadding += $delta);
    }
}
