<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

/**
 * Apply sensible default spacing
 *
 * Specifically:
 * - Add SPACE as per {@see TokenType}::`ADD_SPACE_*`
 * - Suppress SPACE and BLANK as per {@see TokenType}::`SUPPRESS_SPACE_*`
 * - Suppress SPACE and BLANK after open brackets and before close brackets
 * - Propagate indentation from `<?php` tags to subsequent tokens
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
            T_COMMA,
            T_COLON,
            T_OPEN_TAG,
            T_OPEN_TAG_WITH_ECHO,
            T_CLOSE_TAG,
            T_ATTRIBUTE_COMMENT,
            T_MATCH,
            ...TokenType::ADD_SPACE_AROUND,
            ...TokenType::ADD_SPACE_BEFORE,
            ...TokenType::ADD_SPACE_AFTER,
            ...TokenType::SUPPRESS_SPACE_AFTER,
            ...TokenType::SUPPRESS_SPACE_BEFORE,

            // isCloseBracket()
            T_CLOSE_PARENTHESIS,
            T_CLOSE_BRACKET,
            T_CLOSE_BRACE,

            // isOpenBracket()
            T_OPEN_PARENTHESIS,
            T_OPEN_BRACKET,
            T_OPEN_BRACE,
            T_ATTRIBUTE,
            T_CURLY_OPEN,
            T_DOLLAR_OPEN_CURLY_BRACES,

            // endsAlternativeSyntax()
            T_END_ALT_SYNTAX,

            // startsAlternativeSyntax()
            T_COLON,
        ];
    }

    public function processToken(Token $token): void
    {
        // Add SPACE as per TokenType::ADD_SPACE_*
        if ($token->is(TokenType::ADD_SPACE_AROUND)) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
        } elseif ($token->is(TokenType::ADD_SPACE_BEFORE)) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
        } elseif ($token->is(TokenType::ADD_SPACE_AFTER)) {
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
        }

        // Suppress SPACE and BLANK:
        // - as per TokenType::SUPPRESS_SPACE_*
        // - after open brackets and before close brackets
        if (($token->isOpenBracket() && !$token->isStructuralBrace()) ||
                $token->is(TokenType::SUPPRESS_SPACE_AFTER)) {
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
        } elseif ($token->startsAlternativeSyntax()) {
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
        }
        if (($token->isCloseBracket() && !$token->isStructuralBrace()) ||
            ($token->is(TokenType::SUPPRESS_SPACE_BEFORE) &&
                ($token->id !== T_NS_SEPARATOR ||
                    $token->_prev->is([T_NAMESPACE, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_STRING])))) {
            $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
        } elseif ($token->endsAlternativeSyntax()) {
            $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
        }

        if ($token->is([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
            // Propagate indentation from `<?php` tags to subsequent tokens
            $tagIndent = 0;
            $sibling = null;
            $siblingIndent = null;

            $text = '';
            $current = $token;
            while ($current = $current->_prev) {
                $text = $current->text . $text;
                if ($current->id === T_CLOSE_TAG) {
                    break;
                }
            }
            if ($token->_prev && preg_match('/\n(?P<indent>\h+)$/', "\n$text", $matches)) {
                $indent = strlen(str_replace("\t", $this->Formatter->SoftTab, $matches['indent']));
                if ($indent % $this->Formatter->TabSize === 0) {
                    $token->TagIndent = $indent / $this->Formatter->TabSize;

                    /* Look for a `?>` tag in the same context, i.e. with the
                    same BracketStack */
                    $current = $token;
                    while ($current->CloseTag) {
                        if ($current->CloseTag->BracketStack === $token->BracketStack) {
                            $sibling = $current->CloseTag;
                            break;
                        }
                        $current = $current->CloseTag->nextOf(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO);
                        if ($current->IsNull) {
                            break;
                        }
                    }

                    if ($sibling || (!$token->BracketStack && !$token->CloseTag)) {
                        $tagIndent = $token->TagIndent;
                        if ($sibling) {
                            $siblingIndent = $tagIndent;
                        }
                        // Increase the indentation level for tokens between
                        // unenclosed tags
                        if (!$token->BracketStack &&
                                $this->Formatter->IncreaseIndentBetweenUnenclosedTags) {
                            $tagIndent++;
                        }
                    }
                }
            }

            // Apply SPACE between `<?php` and a subsequent `declare` construct
            // in the global scope
            $current = $token;
            if ($token->id === T_OPEN_TAG &&
                    ($declare = $token->next())->id === T_DECLARE &&
                    ($end = $declare->nextSibling(2)) === $declare->EndStatement) {
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext = WhitespaceType::SPACE;
                $current = $end;
            }
            // Add LINE|SPACE after `<?php`
            $current->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;

            /* Preserve one-line `<?php` ... `?>` */
            $last = $token->CloseTag ?: $token->last();
            if ($token !== $last &&
                    $this->preserveOneLine($token, $last)) {
                return;
            }

            // Suppress inner LINE if both ends have adjacent code
            if ($token->CloseTag &&
                    !($nextCode = $token->nextCode())->IsNull &&
                    $nextCode->Index < $token->CloseTag->Index) {
                $lastCode = $token->CloseTag->prevCode();
                if ($nextCode->line === $token->line &&
                        $lastCode->line === $token->CloseTag->line) {
                    $this->preserveOneLine($token, $nextCode, true);
                    $this->preserveOneLine($lastCode, $token->CloseTag, true);
                    // Remove a level of indentation if tokens between
                    // unenclosed tags don't start on a new line
                    if ($tagIndent && !$token->BracketStack &&
                            $this->Formatter->IncreaseIndentBetweenUnenclosedTags) {
                        $tagIndent--;
                    }
                }
            }

            if ($tagIndent && ($next = $token->_next)) {
                $last = $sibling ? $sibling->_prev : $last;
                $this->Formatter->registerCallback(
                    $this,
                    $next,
                    function () use ($tagIndent, $next, $last, $sibling, $siblingIndent) {
                        if (($delta = $tagIndent - $next->indent()) > 0) {
                            $next->collect($last)
                                 ->forEach(
                                     fn(Token $t) => $t->TagIndent += $delta
                                 );
                        }
                        if ($sibling) {
                            $sibling->TagIndent += $siblingIndent - $sibling->indent();
                        }
                    },
                    820
                );
            }

            return;
        }

        /* Add LINE|SPACE before `?>` */
        if ($token->id === T_CLOSE_TAG) {
            $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;

            return;
        }

        // Add SPACE after and suppress SPACE before commas
        if ($token->id === T_COMMA) {
            $token->WhitespaceMaskPrev = WhitespaceType::NONE;
            $token->WhitespaceAfter |= WhitespaceType::SPACE;

            return;
        }

        // Add LINE after labels
        if ($token->id === T_COLON && $token->inLabel()) {
            $token->WhitespaceAfter |= WhitespaceType::LINE;

            return;
        }

        // Add LINE between arms of match expressions
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
                                   ->nextSiblingOf(T_COMMA);
            } while (!$current->IsNull);

            return;
        }

        // Add LINE before and after attributes, suppress BLANK after
        if ($token->id === T_ATTRIBUTE) {
            $token->WhitespaceBefore |= WhitespaceType::LINE;
            $token->ClosedBy->WhitespaceAfter |= WhitespaceType::LINE;
            $token->ClosedBy->WhitespaceMaskNext &= ~WhitespaceType::BLANK;

            return;
        }
        if ($token->id === T_ATTRIBUTE_COMMENT) {
            $token->WhitespaceBefore |= WhitespaceType::LINE;
            $token->WhitespaceAfter |= WhitespaceType::LINE;
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;

            return;
        }

        // Suppress whitespace inside `declare()`
        if ($token->id === T_OPEN_PARENTHESIS && $token->prevCode()->id === T_DECLARE) {
            $token->outer()
                  ->maskInnerWhitespace(WhitespaceType::NONE);
        }
    }
}
