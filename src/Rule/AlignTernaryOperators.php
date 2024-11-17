<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * Align ternary and null coalescing operators with their expressions
 *
 * @api
 */
final class AlignTernaryOperators implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 380,
            self::CALLBACK => 710,
        ][$method] ?? null;
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return [
            \T_QUESTION => true,
            \T_COALESCE => true,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (
                $token->id === \T_QUESTION
                && !($token->Flags & TokenFlag::TERNARY_OPERATOR)
            ) {
                continue;
            }

            // Do nothing if none of the operators in question are at the start
            // of a line
            if (!$token->hasNewlineBefore()
                && ($token->id === \T_COALESCE
                    || !$token->Data[TokenData::OTHER_TERNARY_OPERATOR]->hasNewlineBefore())) {
                continue;
            }

            // If previous ternary or null coalescing operators in this scope
            // have already been aligned, do nothing
            $prevTernary = HangingIndentation::getTernaryContext($token);
            if ($prevTernary && $prevTernary->AlignedWith) {
                $this->setAlignedWith($token, $prevTernary->AlignedWith);
                continue;
            }

            $alignWith =
                ($prevTernary ?: $token)
                    ->PrevCode
                    ->pragmaticStartOfExpression(true);

            $this->setAlignedWith($token, $alignWith);
            $until = HangingIndentation::getTernaryEndOfExpression($token);

            $this->Formatter->registerCallback(
                static::class,
                $token,
                fn() => $this->alignOperators($token, $until, $alignWith)
            );
        }
    }

    private function setAlignedWith(Token $token, Token $alignWith): void
    {
        $token->AlignedWith = $alignWith;
        if ($token->Flags & TokenFlag::TERNARY_OPERATOR) {
            $token->Data[TokenData::OTHER_TERNARY_OPERATOR]->AlignedWith = $alignWith;
        }
    }

    private function alignOperators(Token $token, Token $until, Token $alignWith): void
    {
        $delta =
            $alignWith->alignmentOffset(false)
            + $this->Formatter->TabSize;

        foreach ($token->collect($until) as $token) {
            $token->LinePadding += $delta;
        }
    }
}
