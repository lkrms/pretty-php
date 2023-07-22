<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Catalog\WhitespaceType;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Support\TokenTypeIndex;
use Lkrms\Pretty\Php\Token;

/**
 * Apply sensible default spacing
 *
 * Specifically:
 * - Add SPACE as per {@see TokenTypeIndex}::`$AddSpace*`
 * - Suppress SPACE and BLANK as per {@see TokenTypeIndex}::`$SuppressSpace*`
 * - Suppress SPACE and BLANK after open brackets and before close brackets
 * - Propagate indentation from `<?php` tags to subsequent tokens
 * - Add SPACE or BLANK between `<?php` and a subsequent `declare` construct in
 *   the global scope
 * - Add LINE|SPACE after `<?php` and before `?>`
 * - Preserve one-line `<?php` ... `?>`, or suppress inner LINE if both ends
 *   have adjacent code
 * - Add SPACE after and suppress SPACE before commas
 * - Add LINE after labels
 * - Add LINE between the arms of match expressions
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
        return TokenType::mergeIndexes(
            TokenType::getIndex(
                T_COLON,
                T_COMMA,
                T_MATCH,
                T_OPEN_TAG,
                T_OPEN_TAG_WITH_ECHO,
                T_CLOSE_TAG,
                T_ATTRIBUTE_COMMENT,
            ),
            $this->TypeIndex->OpenBracket,
            $this->TypeIndex->CloseBracketOrEndAltSyntax,
            $this->TypeIndex->AddSpaceAround,
            $this->TypeIndex->AddSpaceBefore,
            $this->TypeIndex->AddSpaceAfter,
            $this->TypeIndex->SuppressSpaceBefore,
            $this->TypeIndex->SuppressSpaceAfter,
        );
    }

    public function processToken(Token $token): void
    {
        // Add SPACE as per TokenTypeIndex::$AddSpace*
        if ($this->TypeIndex->AddSpaceAround[$token->id]) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
        } elseif ($this->TypeIndex->AddSpaceBefore[$token->id]) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
        } elseif ($this->TypeIndex->AddSpaceAfter[$token->id]) {
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
        }

        // Suppress SPACE and BLANK:
        // - as per TokenTypeIndex::$SuppressSpace*
        // - after open brackets and before close brackets
        if (($this->TypeIndex->OpenBracket[$token->id] && !$token->isStructuralBrace()) ||
                $this->TypeIndex->SuppressSpaceAfter[$token->id]) {
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
        } elseif ($token->id === T_COLON && $token->ClosedBy) {  // i.e. `$token->startsAlternativeSyntax()`
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
        }
        if (($this->TypeIndex->CloseBracket[$token->id] && !$token->isStructuralBrace()) ||
            ($this->TypeIndex->SuppressSpaceBefore[$token->id] &&
                ($token->id !== T_NS_SEPARATOR ||
                    $token->_prev->id === T_NAMESPACE ||
                    $token->_prev->id === T_NAME_FULLY_QUALIFIED ||
                    $token->_prev->id === T_NAME_QUALIFIED ||
                    $token->_prev->id === T_NAME_RELATIVE ||
                    $token->_prev->id === T_STRING))) {
            $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
        } elseif ($token->id === T_END_ALT_SYNTAX) {
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

            // Add SPACE or BLANK between `<?php` and a subsequent `declare`
            // construct in the global scope
            $current = $token;
            if ($token->id === T_OPEN_TAG &&
                ($declare = $token->next())->id === T_DECLARE &&
                ($end = $declare->nextSibling(2)) === $declare->EndStatement &&
                (!$this->Formatter->Psr12Compliance ||
                    (!strcasecmp((string) $declare->nextSibling()->inner(), 'strict_types=1') &&
                        ($end->id === T_CLOSE_TAG || $end->next()->id === T_CLOSE_TAG)))) {
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext = WhitespaceType::SPACE;
                $current = $end;
                if ($this->Formatter->Psr12Compliance ||
                        ($end->id === T_CLOSE_TAG || $end->next()->id === T_CLOSE_TAG)) {
                    $token->CloseTag->WhitespaceBefore |= WhitespaceType::SPACE;
                    $token->CloseTag->WhitespaceMaskPrev = WhitespaceType::SPACE;
                }
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

        // Add LINE between the arms of match expressions
        if ($token->id === T_MATCH) {
            $parent = $token->nextSibling(2);
            $arm = $parent->nextCode();
            if ($arm === $parent->ClosedBy) {
                return;
            }
            while (true) {
                $arm = $arm->nextSiblingOf(T_DOUBLE_ARROW)
                           ->nextSiblingOf(T_COMMA);
                if ($arm->IsNull) {
                    break;
                }
                $arm->WhitespaceAfter |= WhitespaceType::LINE;
            }
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
