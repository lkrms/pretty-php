<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Add line breaks between the arms of match expressions where at least one
 * conditional or return expression has a trailing newline
 *
 * This rule ensures formatted `match` statements clearly indicate the
 * expression returned for each condition, unlike here:
 *
 * ```php
 * $out = match ($in) {
 *     0, 1 => 'a', 2, 3,
 *     4 => 'b'
 * };
 * ```
 */
final class SpaceMatch implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 300;
    }

    public function getTokenTypes(): ?array
    {
        return [T_MATCH];
    }

    public function processToken(Token $token): void
    {
        $tokens = $token->nextSibling(2)
                        ->innerSiblings();

        if ($tokens->find(fn(Token $t) =>
                              $t->is([T[','], ...TokenType::OPERATOR_DOUBLE_ARROW]) &&
                                  $t->hasNewlineAfterCode())) {
            $tokens->filter(fn(Token $t) =>
                                $t->isMatchDelimiter())
                   ->addWhitespaceAfter(WhitespaceType::LINE);
        }
    }
}
