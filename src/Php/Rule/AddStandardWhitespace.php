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
 * - Add SPACE as per {@see TokenType}::`ADD_SPACE_*`
 * - Suppress SPACE and BLANK as per {@see TokenType}::`SUPPRESS_SPACE_*`
 * - Suppress SPACE and BLANK after open brackets and before close brackets
 * - Propagate indent from `<?php` to tokens between it and `?>`
 * - Apply SPACE between `<?php` and a subsequent `declare` construct in the
 *   global scope
 * - Add LINE|SPACE after `<?php` and before `?>`
 * - Preserve one-line `<?php` ... `?>`, or suppress inner LINE if both ends
 *   have adjacent code
 * - Add SPACE after and suppress SPACE before commas
 * - Add LINE after labels
 * - Add LINE between arms of match expressions
 * - Add LINE before and after attributes, suppress BLANK after
 * - Suppress whitespace inside `declare()`
 *
 */
final class AddStandardWhitespace implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 80;
    }

    public function getTokenTypes(): array
    {
        return [
            T[','],
            T[':'],
            T_OPEN_TAG,
            T_OPEN_TAG_WITH_ECHO,
            T_CLOSE_TAG,
            T_MATCH,
            ...TokenType::ADD_SPACE_AROUND,
            ...TokenType::ADD_SPACE_BEFORE,
            ...TokenType::ADD_SPACE_AFTER,
            ...TokenType::SUPPRESS_SPACE_AFTER,
            ...TokenType::SUPPRESS_SPACE_BEFORE,

            // isCloseBracket()
            T[')'],
            T[']'],
            T['}'],

            // isOpenBracket()
            T['('],
            T['['],
            T['{'],
            T_ATTRIBUTE,
            T_CURLY_OPEN,
            T_DOLLAR_OPEN_CURLY_BRACES,
        ];
    }

    public function processToken(Token $token): void
    {
        // - Add SPACE as per TokenType::ADD_SPACE_*
        if ($token->is(TokenType::ADD_SPACE_AROUND)) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
        } elseif ($token->is(TokenType::ADD_SPACE_BEFORE)) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
        } elseif ($token->is(TokenType::ADD_SPACE_AFTER)) {
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
        }

        // - Suppress SPACE and BLANK
        //   - as per TokenType::SUPPRESS_SPACE_*
        //   - after open brackets and before close brackets
        if (($token->isOpenBracket() && !$token->isStructuralBrace()) ||
                $token->is(TokenType::SUPPRESS_SPACE_AFTER)) {
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
        }
        if (($token->isCloseBracket() && !$token->isStructuralBrace()) ||
                $token->is(TokenType::SUPPRESS_SPACE_BEFORE)) {
            $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
        }

        if ($token->is([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
            /* - Propagate indent from `<?php` to tokens between it and `?>` */
            $tagIndent = 0;
            if ($token->_prev && preg_match('/\n(?P<indent>\h+)$/', $token->_prev->text, $matches)) {
                $indent = strlen(str_replace("\t", $this->Formatter->SoftTab, $matches['indent']));
                if ($indent % $this->Formatter->TabSize === 0) {
                    $tagIndent = $indent / $this->Formatter->TabSize;
                    /* ...until the next `?>` */
                    if ($token->CloseTag) {
                        $token->CloseTag->TagIndent = $tagIndent;
                    }
                    // Increase the indentation level for tokens between tags
                    $tagIndent++;
                }
            }

            // - Apply SPACE between `<?php` and a subsequent `declare`
            //   construct in the global scope
            // - Add LINE|SPACE after `<?php`
            $current = $token;
            if ($token->id === T_OPEN_TAG &&
                    ($declare = $token->next())->id === T_DECLARE &&
                    ($end = $declare->nextSibling(2)) === $declare->EndStatement) {
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext = WhitespaceType::SPACE;
                $current = $end;
            }
            $current->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;

            /* - Preserve one-line `<?php` ... `?>` */
            $close = $token->CloseTag ?: $token->last();
            if ($token !== $close && $this->preserveOneLine($token, $close)) {
                return;
            }
            // - Suppress inner LINE if both ends have adjacent code
            if ($token->CloseTag &&
                    !($nextCode = $token->nextCode())->IsNull &&
                    $nextCode->Index < $token->CloseTag->Index) {
                $lastCode = $token->CloseTag->prevCode();
                if ($nextCode->line === $token->line &&
                        $lastCode->line === $token->CloseTag->line) {
                    $this->preserveOneLine($token, $nextCode, true);
                    $this->preserveOneLine($lastCode, $token->CloseTag, true);
                    // Remove a level of indentation if tokens between tags
                    // don't start on a new line
                    if ($tagIndent) {
                        $tagIndent--;
                    }
                }
            }

            if ($tagIndent) {
                $close = $token->CloseTag ? $token->CloseTag->_prev : $close;
                $token->collect($close)
                      ->forEach(fn(Token $t) => $t->TagIndent += $tagIndent);
            }

            return;
        }

        /* - Add LINE|SPACE before `?>` */
        if ($token->id === T_CLOSE_TAG) {
            $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;

            return;
        }

        // - Add SPACE after and suppress SPACE before commas
        if ($token->id === T[',']) {
            $token->WhitespaceMaskPrev = WhitespaceType::NONE;
            $token->WhitespaceAfter |= WhitespaceType::SPACE;

            return;
        }

        // - Add LINE after labels
        if ($token->id === T[':'] && $token->inLabel()) {
            $token->WhitespaceAfter |= WhitespaceType::LINE;

            return;
        }

        // - Add LINE between arms of match expressions
        if ($token->id === T_MATCH && !$this->Formatter->MatchesAreLists) {
            $arms = $token->nextSibling(2);
            $current = $arms->nextCode();
            if ($current === $arms->ClosedBy) {
                return;
            }
            $i = 0;
            do {
                if ($i++) {
                    $current->WhitespaceAfter |= WhitespaceType::LINE;
                }
                $current = $current->nextSiblingOf(...TokenType::OPERATOR_DOUBLE_ARROW)
                                   ->nextSiblingOf(T[',']);
            } while (!$current->IsNull);

            return;
        }

        // - Add LINE before and after attributes, suppress BLANK after
        if ($token->id === T_ATTRIBUTE) {
            $token->WhitespaceBefore |= WhitespaceType::LINE;
            $token->ClosedBy->WhitespaceAfter |= WhitespaceType::LINE;
            $token->ClosedBy->WhitespaceMaskNext &= ~WhitespaceType::BLANK;

            return;
        }

        // - Suppress whitespace inside `declare()`
        if ($token->id === T['('] && $token->prevCode()->id === T_DECLARE) {
            $token->outer()
                  ->maskInnerWhitespace(WhitespaceType::NONE);
        }
    }
}
