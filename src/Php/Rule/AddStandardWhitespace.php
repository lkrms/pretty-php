<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Apply sensible default spacing
 *
 * Specifically:
 * - Apply {@see TokenType::ADD_SPACE_AROUND},
 *   {@see TokenType::ADD_SPACE_BEFORE}, {@see TokenType::ADD_SPACE_AFTER},
 *   {@see TokenType::SUPPRESS_SPACE_AFTER},
 *   {@see TokenType::SUPPRESS_SPACE_BEFORE}
 * - Suppress SPACE between brackets and their contents
 * - Add LINE|SPACE after `<?php` and before `?>`
 * - If `<?php` is followed by `declare`, collapse them to `<?php declare`
 * - Add LINE after labels
 * - Suppress whitespace inside `declare()`
 *
 */
final class AddStandardWhitespace implements TokenRule
{
    use TokenRuleTrait {
        __construct as private construct;
    }

    /**
     * @var array<int|string>
     */
    private $AddSpaceAround = TokenType::ADD_SPACE_AROUND;

    public function __construct(Formatter $formatter)
    {
        $this->construct($formatter);

        // Add tokens in ADD_SPACE_BEFORE and ADD_SPACE_AFTER, but not in
        // ADD_SPACE_AROUND, to $this->AddSpaceAround
        array_push(
            $this->AddSpaceAround,
            ...array_diff(
                array_intersect(
                    TokenType::ADD_SPACE_BEFORE,
                    TokenType::ADD_SPACE_AFTER
                ),
                TokenType::ADD_SPACE_AROUND
            )
        );
    }

    public function getTokenTypes(): ?array
    {
        return [
            T[','],
            T[':'],
            T_OPEN_TAG,
            T_OPEN_TAG_WITH_ECHO,
            T_CLOSE_TAG,

            T[')'],    // isCloseBracket()
            T[']'],
            T['}'],

            T['('],    // isOpenBracket()
            T['['],
            T['{'],
            T_ATTRIBUTE,
            T_CURLY_OPEN,
            T_DOLLAR_OPEN_CURLY_BRACES,

            ...TokenType::ADD_SPACE_AROUND,
            ...TokenType::ADD_SPACE_BEFORE,
            ...TokenType::ADD_SPACE_AFTER,
            ...TokenType::SUPPRESS_SPACE_AFTER,
            ...TokenType::SUPPRESS_SPACE_BEFORE,
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->is($this->AddSpaceAround)) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceAfter  |= WhitespaceType::SPACE;
        } elseif ($token->is(TokenType::ADD_SPACE_BEFORE)) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
        } elseif ($token->is(TokenType::ADD_SPACE_AFTER)) {
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
        }

        if (($token->isOpenBracket() && !$token->isStructuralBrace()) ||
                $token->is(TokenType::SUPPRESS_SPACE_AFTER)) {
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
        }

        if (($token->isCloseBracket() && !$token->isStructuralBrace()) ||
                $token->is(TokenType::SUPPRESS_SPACE_BEFORE)) {
            $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
        }

        if ($token->is([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
            // Place `declare` beside `<?php`
            if ($token->is(T_OPEN_TAG) &&
                    ($declare = $token->next())->is(T_DECLARE) &&
                    ($end = $declare->nextSibling(2)) === $declare->endOfStatement()) {
                $token->WhitespaceAfter   |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext = WhitespaceType::SPACE;
                $token                     = $end;
            }
            $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;

            return;
        }

        if ($token->is(T_CLOSE_TAG)) {
            $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;

            return;
        }

        if ($token->is(T[','])) {
            $token->WhitespaceMaskPrev = WhitespaceType::NONE;
            $token->WhitespaceAfter   |= WhitespaceType::SPACE;

            return;
        }

        if ($token->is(T[':']) && $token->inLabel()) {
            $token->WhitespaceAfter    |= WhitespaceType::LINE;
            $token->WhitespaceMaskNext |= WhitespaceType::LINE;

            return;
        }

        // Suppress whitespace in the directive section of `declare` blocks
        if ($token->is(T['(']) && $token->prevCode()->is(T_DECLARE)) {
            $first = $token->inner()
                           ->forEach(fn(Token $t) =>
                               $t->WhitespaceMaskNext = WhitespaceType::NONE)
                           ->first();
            !$first || $first->WhitespaceMaskPrev = WhitespaceType::NONE;
        }
    }
}
