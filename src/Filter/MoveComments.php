<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\FilterTrait;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\GenericToken;
use Lkrms\PrettyPHP\TokenTypeIndex;
use Salient\Utility\Exception\ShouldNotHappenException;

/**
 * Move comments if necessary for correct placement of adjacent delimiters and
 * operators
 *
 * @api
 */
final class MoveComments implements Filter
{
    use FilterTrait;

    /**
     * Movable tokens allowed before newlines/comments
     *
     * @var array<int,bool>
     */
    private array $BeforeCommentIndex;

    /**
     * Movable tokens allowed after newlines/comments
     *
     * @var array<int,bool>
     */
    private array $AfterCommentIndex;

    private bool $NeedsFnDoubleArrow;

    // --

    private int $Count;
    /** @var array<int,bool> */
    private array $FnDoubleArrow;

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $idx = $this->Idx->withPreserveNewline();

        $this->BeforeCommentIndex = TokenTypeIndex::intersect(
            $this->Idx->Movable,
            $idx->AllowNewlineAfter,
        );

        $this->AfterCommentIndex = TokenTypeIndex::intersect(
            $this->Idx->Movable,
            $idx->AllowNewlineBefore,
        );

        $this->NeedsFnDoubleArrow = $this->Formatter->NewlineBeforeFnDoubleArrow;
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $tokens = [];
        $this->Tokens = &$tokens;
        $this->Count = 0;
        if ($this->NeedsFnDoubleArrow) {
            $this->FnDoubleArrow = [];
        }
    }

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        $this->Tokens = &$tokens;
        $this->Count = count($tokens);

        if ($this->NeedsFnDoubleArrow) {
            foreach ($tokens as $i => $token) {
                if ($token->id === \T_FN) {
                    if (!$this->getNextSiblingOf($i, $this->Count, \T_DOUBLE_ARROW, $j)) {
                        // @codeCoverageIgnoreStart
                        throw new ShouldNotHappenException('Invalid arrow function');
                        // @codeCoverageIgnoreEnd
                    }
                    $this->FnDoubleArrow[$j] = true;
                }
            }
        }

        // Rearrange one or more of these:
        //
        //     1*(T_COMMENT / T_DOC_COMMENT)
        //     1*(T_COMMA / T_SEMICOLON / T_EQUAL)
        //
        // Into this:
        //
        //     1*(T_COMMA / T_SEMICOLON / T_EQUAL)
        //     1*(T_COMMENT / T_DOC_COMMENT)
        //
        $this->swapTokens(
            $this->Idx->Comment,
            $this->BeforeCommentIndex,
            true,
        );

        // And one or more of these:
        //
        //     1*(T_LOGICAL_NOT)
        //     1*(T_COMMENT / T_DOC_COMMENT)
        //
        // Into this:
        //
        //     1*(T_COMMENT / T_DOC_COMMENT)
        //     1*(T_LOGICAL_NOT)
        //
        $this->swapTokens(
            $this->AfterCommentIndex,
            $this->Idx->Comment,
            false,
        );

        return $tokens;
    }

    /**
     * @param array<int,bool> $firstIdx
     * @param array<int,bool> $lastIdx
     */
    private function swapTokens(
        array $firstIdx,
        array $lastIdx,
        bool $firstIsComment
    ): void {
        for ($i = 1; $i < $this->Count; $i++) {
            $token = $this->Tokens[$i];

            if (!$firstIdx[$token->id] || !$this->checkToken($i)) {
                continue;
            }

            // Index of first token in `$firstIdx`
            $first = $i;
            // Index of last token in `$lastIdx`
            $last = null;
            // Tokens in `$firstIdx`
            $firstTokens = [];
            // Tokens in `$lastIdx`
            $lastTokens = [];

            $i--;
            while (++$i < $this->Count) {
                $token = $this->Tokens[$i];

                if (
                    !$firstTokens
                    || ($firstIdx[$token->id] && $this->checkToken($i))
                ) {
                    $firstTokens[$i] = $token;
                    continue;
                }

                if ($lastIdx[$token->id] && $this->checkToken($i, true)) {
                    $last = $i;
                    $lastTokens[$i] = $token;
                    continue;
                }

                break;
            }

            if ($last === null) {
                continue;
            }

            $length = $last - $first + 1;

            // Discard any tokens in `$firstIdx` collected after the last token
            // in `$lastIdx`
            $firstTokens = array_slice($firstTokens, 0, $length - count($lastTokens), true);

            if ($firstIsComment) {
                $lineTokens = $lastTokens;
                $prev = $this->Tokens[$first - 1];
            } else {
                $lineTokens = $firstTokens;
                $prev = $this->Tokens[$last];
            }
            $line = $prev->line + substr_count($prev->text, "\n");
            foreach ($lineTokens as $token) {
                $token->line = $line;
            }

            array_splice($this->Tokens, $first, $length, $lastTokens + $firstTokens);
        }
    }

    private function checkToken(int $i, bool $isLast = false): bool
    {
        $token = $this->Tokens[$i];

        if ($token->id === \T_COLON) {
            // The following code replicates most of `Token::getColonType()`,
            // which can't be used in this context
            if ($this->isColonAltSyntaxDelimiter($i)) {
                // Allow comments AFTER alternative syntax delimiters
                return $isLast;
            }

            $parent = $this->getParent($i, $parentIndex);
            if ($parent && $this->isColonSwitchCaseDelimiter($i, $parentIndex)) {
                // Allow comments AFTER switch case delimiters
                return $isLast;
            }

            /** @var GenericToken */
            $prevCode = $this->getPrevCode($i, $prevCodeIndex);
            if (
                $parent
                && $parent->id === \T_OPEN_PARENTHESIS
                && $prevCode->id === \T_STRING
                && ($prevCode2 = $this->getPrevCode($prevCodeIndex))
                && ($prevCode2 === $parent || $prevCode2->id === \T_COMMA)
            ) {
                // Allow comments AFTER named argument delimiters
                return $isLast;
            }

            if (
                $prevCode->id === \T_STRING
                && ($prevCode2 = $this->getPrevCode($prevCodeIndex))
                && $prevCode2->id === \T_ENUM
            ) {
                // Allow comments AFTER backed enumeration type delimiters
                return $isLast;
            }

            if ($prevCode->id === \T_CLOSE_PARENTHESIS) {
                $prev = $this->getPrevSibling($prevCodeIndex, 1, $prevIndex);
                if (
                    $prev
                    && $prev->id === \T_USE
                    && ($prevCode2 = $this->getPrevCode($prevIndex, $prevCode2Index))
                    && $prevCode2->id === \T_CLOSE_PARENTHESIS
                ) {
                    $prev = $this->getPrevSibling($prevCode2Index, 1, $prevIndex);
                }

                while ($prev && $this->Idx->FunctionIdentifier[$prev->id]) {
                    $prev = $this->getPrevSibling($prevIndex, 1, $prevIndex);
                }

                if ($prev && ($prev->id === \T_FUNCTION || $prev->id === \T_FN)) {
                    // Allow comments AFTER return type delimiters
                    return $isLast;
                }
            }

            while ($prevCode->id === \T_STRING && (
                ($prev = $this->getPrevSibling($prevCodeIndex, 1, $prevIndex))
                && $prev->id === \T_COLON
            )) {
                if ($this->isColonAltSyntaxDelimiter($prevIndex) || (
                    $parent
                    && $this->isColonSwitchCaseDelimiter($prevIndex, $parentIndex)
                )) {
                    // Allow comments AFTER label delimiters in alternative
                    // syntax or switch case statements
                    return $isLast;
                }
                /** @var GenericToken */
                $prevCode = $this->getPrevCode($prevIndex, $prevCodeIndex);
            }
            if ($prevCode->id === \T_STRING && (
                !($prev = $this->getPrevSibling($prevCodeIndex, 1, $prevIndex)) || (
                    $prev->id === \T_SEMICOLON
                    || $prev->id === \T_CLOSE_BRACE
                    || $this->rangeHasCloseTag($prevIndex + 1, $prevCodeIndex - 1)
                )
            )) {
                // Allow comments AFTER label delimiters in other contexts
                return $isLast;
            }

            // Allow comments BEFORE ternary operators
            return !$isLast;
        }

        if ($token->id === \T_QUESTION) {
            // Allow comments BEFORE ternary operators and nullable types
            return !$isLast;
        }

        if ($token->id === \T_DOUBLE_ARROW) {
            if ($this->NeedsFnDoubleArrow && ($this->FnDoubleArrow[$i] ?? false)) {
                // Allow comments BEFORE `=>` in arrow functions if enabled
                return !$isLast;
            }

            // Allow comments AFTER `=>` otherwise
            return $isLast;
        }

        return true;
    }

    private function rangeHasCloseTag(int $i, int $j): bool
    {
        while ($i <= $j) {
            if ($this->Tokens[$i++]->id === \T_CLOSE_TAG) {
                return true;
            }
        }
        return false;
    }
}
