<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenFlagMask;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Support\TokenIndentDelta;
use Salient\Utility\Arr;
use Salient\Utility\Str;
use JsonSerializable;

class Token extends GenericToken implements JsonSerializable
{
    use NavigableTokenTrait;
    use ContextAwareTokenTrait;
    use CollectibleTokenTrait;

    /**
     * The starting column (1-based) of the token
     */
    public int $column = -1;

    public int $TagIndent = 0;

    /**
     * Indentation levels to ignore until the token is rendered, e.g. those
     * applied to unenclosed control structure bodies
     */
    public int $PreIndent = 0;

    /**
     * Indentation levels implied by the token's enclosing brackets
     */
    public int $Indent = 0;

    /**
     * Indentation levels to remove when the token is rendered and to ignore
     * otherwise, e.g. to indent `case` and `default` statements correctly
     */
    public int $Deindent = 0;

    public int $HangingIndent = 0;
    public ?int $HangingIndentParentType = null;

    /**
     * The token that triggered each level of hanging indentation applied to the
     * token
     *
     * @var Token[]
     */
    public array $HangingIndentStack = [];

    /**
     * The context of each level of hanging indentation applied to the token
     *
     * @var array<array<array<Token|null>|Token>>
     */
    public array $HangingIndentContextStack = [];

    /**
     * Parent tokens associated with at least one level of hanging indentation
     * applied to the token
     *
     * @var Token[]
     */
    public array $HangingIndentParentStack = [];

    /**
     * Each entry represents a parent token associated with at least one level
     * of collapsible indentation applied to the token
     *
     * Parent token index => levels of collapsible indentation applied
     *
     * @var array<int,int>
     */
    public array $HangingIndentParentLevels = [];

    public int $LinePadding = 0;
    public int $LineUnpadding = 0;
    public int $Padding = 0;
    public ?string $HeredocIndent = null;
    public ?Token $AlignedWith = null;

    /**
     * Bitmask representing whitespace between the token and its predecessor
     */
    public int $WhitespaceBefore = WhitespaceType::NONE;

    /**
     * Bitmask representing whitespace between the token and its successor
     */
    public int $WhitespaceAfter = WhitespaceType::NONE;

    /**
     * Bitmask applied to whitespace between the token and its predecessor
     */
    public int $WhitespaceMaskPrev = WhitespaceType::ALL;

    /**
     * Bitmask applied to whitespace between the token and its successor
     */
    public int $WhitespaceMaskNext = WhitespaceType::ALL;

    /**
     * Secondary bitmask representing whitespace between the token and its
     * predecessor
     *
     * Values added to this bitmask MUST NOT BE REMOVED.
     */
    public int $CriticalWhitespaceBefore = WhitespaceType::NONE;

    /**
     * Secondary bitmask representing whitespace between the token and its
     * successor
     *
     * Values added to this bitmask MUST NOT BE REMOVED.
     */
    public int $CriticalWhitespaceAfter = WhitespaceType::NONE;

    /**
     * Secondary bitmask applied to whitespace between the token and its
     * predecessor
     *
     * Values removed from this bitmask MUST NOT BE RESTORED.
     */
    public int $CriticalWhitespaceMaskPrev = WhitespaceType::ALL;

    /**
     * Secondary bitmask applied to whitespace between the token and its
     * successor
     *
     * Values removed from this bitmask MUST NOT BE RESTORED.
     */
    public int $CriticalWhitespaceMaskNext = WhitespaceType::ALL;

    public int $OutputLine = -1;
    public int $OutputPos = -1;
    public int $OutputColumn = -1;

    final public function isMatchBrace(): bool
    {
        $current = $this->OpenedBy ?: $this;

        return
            $current->id === \T_OPEN_BRACE
            && ($prev = $current->PrevSibling)
            && ($prev = $prev->PrevSibling)
            && $prev->id === \T_MATCH;
    }

    final public function isMatchDelimiter(): bool
    {
        return
            $this->id === \T_COMMA
            && $this->Parent
            && $this->Parent->isMatchBrace();
    }

    final public function isDelimiterBetweenMatchArms(): bool
    {
        return
            $this->isMatchDelimiter()
            && $this->prevSiblingOf(\T_COMMA, \T_DOUBLE_ARROW)->id === \T_DOUBLE_ARROW;
    }

    final public function isDelimiterBetweenMatchExpressions(): bool
    {
        return
            $this->isMatchDelimiter()
            && $this->prevSiblingOf(\T_COMMA, \T_DOUBLE_ARROW)->id !== \T_DOUBLE_ARROW;
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        $a['id'] = $this->getTokenName();
        $a['text'] = $this->text;
        $a['line'] = $this->line;
        $a['pos'] = $this->pos;
        $a['column'] = $this->column;

        if ($this->SubType !== null) {
            $a['SubType'] = TokenSubType::toName($this->SubType);
        }

        $a['PrevSibling'] = $this->PrevSibling;
        $a['NextSibling'] = $this->NextSibling;
        $a['Parent'] = $this->Parent;
        $a['String'] = $this->String;
        $a['Heredoc'] = $this->Heredoc;
        $a['ExpandedText'] = $this->ExpandedText;
        $a['OriginalText'] = $this->OriginalText;
        $a['Statement'] = $this->Statement;
        $a['EndStatement'] = $this->EndStatement;
        $a['Expression'] = $this->Expression;
        $a['EndExpression'] = $this->EndExpression;

        if ($this->Flags) {
            $flags = [];
            foreach (TokenFlag::cases() as $name => $value) {
                if (($this->Flags & $value) === $value) {
                    $flags[] = $name;
                }
            }
            if ($flags) {
                $a['Flags'] = implode('|', $flags);
            }
        }

        $a['OtherTernaryOperator'] = $this->OtherTernaryOperator;
        $a['TagIndent'] = $this->TagIndent;
        $a['PreIndent'] = $this->PreIndent;
        $a['Indent'] = $this->Indent;
        $a['Deindent'] = $this->Deindent;
        $a['HangingIndent'] = $this->HangingIndent;
        $a['HangingIndentParentType'] = $this->HangingIndentParentType;
        $a['HangingIndentStack'] = $this->HangingIndentStack;
        $a['HangingIndentParentStack'] = $this->HangingIndentParentStack;

        foreach ($this->HangingIndentContextStack as $i => $entry) {
            foreach ($entry as $j => $entry) {
                if (is_array($entry)) {
                    foreach ($entry as $k => $entry) {
                        $a['HangingIndentContextStack'][$i][$j][$k] = (string) $entry;
                    }
                    continue;
                }
                $a['HangingIndentContextStack'][$i][$j] = (string) $entry;
            }
        }

        $a['HangingIndentParentLevels'] = $this->HangingIndentParentLevels;
        $a['LinePadding'] = $this->LinePadding;
        $a['LineUnpadding'] = $this->LineUnpadding;
        $a['Padding'] = $this->Padding;
        $a['HeredocIndent'] = $this->HeredocIndent;
        $a['AlignedWith'] = $this->AlignedWith;
        $a['ChainOpenedBy'] = $this->ChainOpenedBy;
        $a['WhitespaceBefore'] = WhitespaceType::toWhitespace($this->WhitespaceBefore);
        $a['WhitespaceAfter'] = WhitespaceType::toWhitespace($this->WhitespaceAfter);
        $a['WhitespaceMaskPrev'] = $this->WhitespaceMaskPrev;
        $a['WhitespaceMaskNext'] = $this->WhitespaceMaskNext;
        $a['CriticalWhitespaceBefore'] = $this->CriticalWhitespaceBefore;
        $a['CriticalWhitespaceAfter'] = $this->CriticalWhitespaceAfter;
        $a['CriticalWhitespaceMaskPrev'] = $this->CriticalWhitespaceMaskPrev;
        $a['CriticalWhitespaceMaskNext'] = $this->CriticalWhitespaceMaskNext;
        $a['OutputLine'] = $this->OutputLine;
        $a['OutputPos'] = $this->OutputPos;
        $a['OutputColumn'] = $this->OutputColumn;

        foreach ($a as $key => &$value) {
            if (
                $value === null
                || $value === []
                || ($value === false && $key !== 'Expression')
            ) {
                unset($a[$key]);
                continue;
            }
            if ($value instanceof Token) {
                $value = (string) $value;
                continue;
            }
            if (Arr::of($value, Token::class)) {
                foreach ($value as &$token) {
                    $token = (string) $token;
                }
                unset($token);
            }
        }
        unset($value);

        return $a;
    }

    final public function canonicalClose(): Token
    {
        return $this->ClosedBy ?: $this;
    }

    /**
     * @api
     */
    final public function wasFirstOnLine(): bool
    {
        if ($this->IsNull) {
            return false;
        }
        do {
            $prev = ($prev ?? $this)->Prev;
            if (!$prev) {
                return true;
            }
        } while ($prev->IsVirtual);
        $prevCode = $prev->OriginalText ?: $prev->text;
        $prevNewlines = substr_count($prevCode, "\n");

        return $this->line > ($prev->line + $prevNewlines)
            || $prevCode[-1] === "\n";
    }

    /**
     * @api
     */
    final public function wasLastOnLine(): bool
    {
        if ($this->IsNull) {
            return false;
        }
        do {
            $next = ($next ?? $this)->Next;
            if (!$next) {
                return true;
            }
        } while ($next->IsVirtual);
        $code = $this->OriginalText ?: $this->text;
        $newlines = substr_count($code, "\n");

        return ($this->line + $newlines) < $next->line
            || $code[-1] === "\n";
    }

    private function byOffset(string $name, int $offset): Token
    {
        $t = $this;
        for ($i = 0; $i < $offset; $i++) {
            $t = $t->{"_$name"} ?? null;
        }

        return $t ?: $this->null();
    }

    public function prev(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->Prev ?: $this->null();

            case 2:
                return ($this->Prev->Prev ?? null) ?: $this->null();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    public function next(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->Next ?: $this->null();

            case 2:
                return ($this->Next->Next ?? null) ?: $this->null();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    public function prevCode(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->PrevCode ?: $this->null();

            case 2:
                return ($this->PrevCode->PrevCode ?? null) ?: $this->null();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    public function nextCode(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->NextCode ?: $this->null();

            case 2:
                return ($this->NextCode->NextCode ?? null) ?: $this->null();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    public function prevSibling(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->PrevSibling ?: $this->null();

            case 2:
                return ($this->PrevSibling->PrevSibling ?? null) ?: $this->null();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    public function nextSibling(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->NextSibling ?: $this->null();

            case 2:
                return ($this->NextSibling->NextSibling ?? null) ?: $this->null();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    final public function parent(): Token
    {
        $current = $this->OpenedBy ?: $this;

        return $current->Parent ?: $this->null();
    }

    final public function startOfLine(bool $ignoreComments = true): Token
    {
        $current = $this;
        while (!$current->hasNewlineBefore()
                && ($ignoreComments
                    || !($current->isMultiLineComment() && $current->hasNewline()))
                && $current->id !== \T_END_HEREDOC
                && $current->Prev) {
            $current = $current->Prev;
        }

        return $current;
    }

    final public function endOfLine(bool $ignoreComments = true): Token
    {
        $current = $this;
        while (!$current->hasNewlineAfter()
                && ($ignoreComments
                    || !($current->isMultiLineComment() && $current->hasNewline()))
                && $current->id !== \T_START_HEREDOC
                && $current->Next) {
            $current = $current->Next;
        }

        return $current;
    }

    /**
     * Get the token's offset relative to the most recent alignment token or the
     * start of the line, whichever is closest
     *
     * An alignment token is a token where {@see Token::$AlignedWith} is set.
     *
     * Whitespace at the start of the line is ignored.
     *
     * @param bool $includeToken If `true` (the default), the offset includes
     * the token itself.
     * @param bool $allowSelfAlignment If `true`, the token itself is considered
     * an alignment token candidate.
     */
    public function alignmentOffset(bool $includeToken = true, bool $allowSelfAlignment = false): int
    {
        $startOfLine = $this->startOfLine();
        $from =
            $startOfLine
                ->collect($this)
                ->reverse()
                ->find(
                    fn(Token $t, ?Token $next) =>
                        ($t->AlignedWith
                            && ($allowSelfAlignment || $t !== $this))
                        || ($next
                            && $next === $this->AlignedWith)
                ) ?: $startOfLine;

        if ($includeToken) {
            $code = $from->collect($this)->render(true);
        } else {
            $code = $from->collect($this->Prev)->render(true, true, false);
        }
        $offset = mb_strlen($code);
        // Handle strings with embedded newlines
        if (($newline = mb_strrpos($code, "\n")) !== false) {
            $newLinePadding = $offset - $newline - 1;
            $offset = $newLinePadding - ($this->LinePadding - $this->LineUnpadding);
        } else {
            $offset -= $from->hasNewlineBefore() ? $from->LineUnpadding : 0;
        }

        return $offset;
    }

    final public function continuesControlStructure(): bool
    {
        return $this->is([\T_CATCH, \T_FINALLY, \T_ELSEIF, \T_ELSE])
            || ($this->id === \T_WHILE && $this->Statement !== $this);
    }

    /**
     * Get the first sibling in the token's expression
     *
     * @param bool $containUnenclosed If `true`, braces are imagined around
     * control structures with unenclosed bodies. The default is `false`.
     */
    final public function pragmaticStartOfExpression(bool $containUnenclosed = false): Token
    {
        // If the token is an object operator, return the first token in the
        // chain
        if ($this->TypeIndex->Chain[$this->id]) {
            $current = $this;
            $first = null;
            while (($current = $current->PrevSibling)
                    && $this->Expression === $current->Expression
                    && $current->is([
                        \T_DOUBLE_COLON,
                        \T_NAME_FULLY_QUALIFIED,
                        \T_NAME_QUALIFIED,
                        \T_NAME_RELATIVE,
                        \T_VARIABLE,
                        ...TokenType::CHAIN_PART
                    ])) {
                $first = $current;
            }

            return $first->_pragmaticStartOfExpression($this);
        }

        // If the token is between `?` and `:` in a ternary expression, return
        // the first token after `?`
        $ternary1 =
            $this->prevSiblings()
                 ->find(fn(Token $t) =>
                            ($t->Flags & TokenFlag::TERNARY_OPERATOR)
                                && $t->id === \T_QUESTION);
        if ($ternary1 && $ternary1->OtherTernaryOperator->Index > $this->Index) {
            return $ternary1->NextCode->_pragmaticStartOfExpression($this);
        }

        // Otherwise, traverse expressions until an appropriate terminator is
        // reached
        $current = $this->OpenedBy ?: $this;
        $last = $current;
        $i = -1;
        while (true) {
            $i++;
            // If this is the first iteration, or `$current` is an ignored
            // expression boundary, move back to a sibling that isn't a
            // terminator
            while ($current && $current->Expression === false) {
                if ($i && !(($current->Flags & TokenFlag::TERNARY_OPERATOR)
                        || $current->is(TokenType::OPERATOR_COMPARISON_EXCEPT_COALESCE))) {
                    break;
                }
                $i++;
                [$last, $current] =
                    [$current, $current->PrevSibling];
            }
            $current = $current->Expression ?? null;
            if (!$current) {
                return $last->_pragmaticStartOfExpression($this);
            }

            // Honour imaginary braces around control structures with unenclosed
            // bodies if needed
            if ($containUnenclosed) {
                if ($this->TypeIndex->HasStatementWithOptionalBraces[$current->id]
                        && ($body = $current->NextSibling)->id !== \T_OPEN_BRACE
                        && $current->EndExpression->withTerminator()->Index >= $this->Index) {
                    return $body->_pragmaticStartOfExpression($this);
                }
                if ($this->TypeIndex->HasExpressionAndStatementWithOptionalBraces[$current->id]
                        && ($body = $current->NextSibling->NextSibling)->id !== \T_OPEN_BRACE
                        && $current->EndExpression->withTerminator()->Index >= $this->Index) {
                    return $body->_pragmaticStartOfExpression($this);
                }
            }

            // Preemptively traverse the boundary so subsequent code can simply
            // `continue`
            [$last, $current] =
                [$current, $current->PrevSibling->PrevSibling ?? null];

            // Don't terminate if the current token continues a control
            // structure
            if ($last->continuesControlStructure()) {
                continue;
            }

            // Undo the boundary traversal
            $current = $last->PrevSibling;
        }
    }

    private function _pragmaticStartOfExpression(Token $requester): Token
    {
        if ($requester !== $this
                && $this->is([\T_RETURN, \T_YIELD, \T_YIELD_FROM])) {
            return $this->NextCode;
        }

        return $this;
    }

    /**
     * If the token were moved to the right, get the last token that would move
     * with it
     *
     * Statement separators (e.g. `,` and `;`) are not part of expressions and
     * are not returned unless {@see Token::pragmaticEndOfExpression()} is
     * called on them directly.
     *
     * @param bool $containUnenclosed If `true` (the default), braces are
     * imagined around control structures with unenclosed bodies.
     */
    final public function pragmaticEndOfExpression(
        bool $containUnenclosed = true,
        bool $containDeclaration = true
    ): Token {
        // If the token is a statement terminator, there is no expression to
        // move
        if ($this->EndStatement === $this && $this->Expression === false) {
            return $this;
        }

        // If the token is part of a declaration with an adjacent body (class,
        // function, interface, etc.), return the token that precedes the
        // opening brace of the body to ensure it maintains its original
        // position
        if (
            $containDeclaration
            && $this->Expression
            && ($parts = $this->skipPrevSiblingsToDeclarationStart()->declarationParts())->hasValue($this, true)
            && $parts->hasOneOf(...TokenType::DECLARATION_TOP_LEVEL)
            // Exclude anonymous functions, which can move as needed
            && ($last = $parts->last()->skipPrevSiblingsFrom(
                $this->TypeIndex->Ampersand
            ))->id !== \T_FUNCTION
            // Anonymous classes are a special case where if there is a newline
            // before `class`, the first hanging indent in the declaration is
            // propagated to the whole class, and a subsequent indent for the
            // `implements` list is only propagated to other interfaces in the
            // list:
            //
            // ```php
            // <?php
            // $foo = new
            //     #[Attribute]
            //     class implements
            //         Bar,
            //         Baz
            //     {
            //         // ...
            //     };
            // ```
            //
            // But if there is no newline before `class`, no indents are
            // propagated to the whole class:
            //
            // ```php
            // <?php
            // $foo = new class implements
            //     Bar,
            //     Baz
            // {
            //     // ...
            // };
            // ```
            && (($first = $parts->first())->id !== \T_NEW
                || !(($class = $parts->getFirstOf(\T_CLASS))
                    && $class->PrevCode->hasNewlineBeforeNextCode())
                || $first->NextCode !== $this)
            && !($end = $last->nextSiblingOf(\T_OPEN_BRACE))->IsNull
            && $end->Index < $this->EndStatement->Index
        ) {
            return $end->PrevCode;
        }

        // If the token is an expression boundary, return the last token in the
        // statement
        if (!$containUnenclosed && $this->Expression === false) {
            $end = $this->EndStatement ?: $this;
            return
                $end === $this
                    ? $end
                    : $end->withoutTerminator();
        }

        // If the token is an object operator, return the last token in the
        // chain
        if ($this->TypeIndex->Chain[$this->id]) {
            $current = $this;
            do {
                $last = $current;
            } while (($current = $current->NextSibling)
                && $this->Expression === $current->Expression
                && $this->TypeIndex->ChainPart[$current->id]);

            return $last->ClosedBy ?: $last;
        }

        // If the token is between `?` and `:` in a ternary expression, return
        // the last token before `:`
        $current = $this;
        while ($current = $current->PrevSibling) {
            if (($current->Flags & TokenFlag::TERNARY_OPERATOR)
                    && $current->id === \T_QUESTION) {
                if ($current->OtherTernaryOperator->Index > $this->Index) {
                    return $current->OtherTernaryOperator->PrevCode;
                }
                break;
            }
        }

        // Otherwise, traverse siblings by expression until none remain or an
        // appropriate terminator is found
        $current = $this->OpenedBy ?: $this;
        $inSwitchCase = $current->inSwitchCase();

        while ($current->EndExpression) {
            $current = $current->EndExpression;
            $terminator =
                $current->NextSibling
                && $current->NextSibling->Expression === false
                    ? $current->NextSibling
                    : $current;
            $next = $terminator->NextSibling;

            if (!$next) {
                return $current;
            }

            [$last, $current] = [$current, $next];

            // Don't terminate if the token between expressions is a ternary
            // operator or an expression terminator other than `)`, `]` and `;`
            if (($terminator->Flags & TokenFlag::TERNARY_OPERATOR)
                    || $this->TypeIndex->ExpressionDelimiter[$terminator->id]) {
                continue;
            }

            // Don't terminate `case` and `default` statements until the next
            // `case` or `default` is reached
            if ($inSwitchCase && $next->id !== \T_CASE && $next->id !== \T_DEFAULT) {
                continue;
            }

            // Don't terminate if the next token continues a control structure
            if ($next->id === \T_CATCH || $next->id === \T_FINALLY) {
                continue;
            }
            if (($next->id === \T_ELSEIF || $next->id === \T_ELSE)
                && (!$containUnenclosed
                    || $terminator->id === \T_CLOSE_BRACE
                    || $terminator->prevSiblingOf(\T_IF, \T_ELSEIF)->Index >= $this->Index)) {
                continue;
            }
            if ($next->id === \T_WHILE
                && $next->Statement !== $next
                && (!$containUnenclosed
                    || $terminator->id === \T_CLOSE_BRACE
                    || $next->Statement->Index >= $this->Index)) {
                continue;
            }

            // Otherwise, terminate
            return $last;
        }

        return $current;
    }

    final public function adjacent(int ...$types): ?Token
    {
        $current = $this->ClosedBy ?: $this;
        if (!$types) {
            $types = [\T_CLOSE_BRACE, \T_CLOSE_BRACKET, \T_CLOSE_PARENTHESIS, \T_COMMA];
        }
        $outer = $current->withNextCodeWhile(true, ...$types)->last();
        if (!$outer
                || !$outer->NextCode
                || !$outer->EndStatement
                || $outer->EndStatement->Index <= $outer->NextCode->Index) {
            return null;
        }
        return $outer->NextCode;
    }

    /**
     * Get the first token of an expression, statement or block in a parent
     * scope that appears between the token and the end of the line
     *
     * In this example, the token adjacent to `$b` is `{`:
     *
     * ```php
     * if ($c &&
     *         ($a || $b)) {
     *     // ...
     * }
     * ```
     *
     * Returns `null` if:
     *
     * - there are no tokens adjacent to the token
     * - neither the token nor its parent have a close bracket to establish a
     *   distinct scope for subsequent tokens
     * - `$requireAlignedWith` is `true` (the default) and there are no tokens
     *   between the adjacent token and the end of the line with an
     *   {@see Token::$AlignedWith} token
     */
    final public function adjacentBeforeNewline(bool $requireAlignedWith = true): ?Token
    {
        // Return `null` if neither the token nor its parent have a close
        // bracket
        $current = $this->ClosedBy ?: $this;
        if (!$current->OpenedBy) {
            /** @var static|null */
            $current = $current->Parent->ClosedBy ?? null;
            if (!$current) {
                return null;
            }
        }

        // Find the last `)`, `]`, `}`, or `,` on the same line as the close
        // bracket and assign it to `$outer`
        $eol = $this->endOfLine();
        $outer = $current->withNextCodeWhile(false, \T_CLOSE_BRACE, \T_CLOSE_BRACKET, \T_CLOSE_PARENTHESIS, \T_COMMA)
                         ->filter(fn(Token $t) => $t->Index <= $eol->Index)
                         ->last();

        // If it's a `,`, move to the first token of the next expression on the
        // same line and assign it to `$next`
        $next = $outer;
        while ($next
                && $next->Expression === false
                && $next->NextSibling
                && $next->NextSibling->Index <= $eol->Index) {
            $next = $next->NextSibling;
        }

        // Return `null` if the first code token after `$outer` is on a
        // subsequent line, or if neither `$outer` nor `$next` belong to a
        // statement that continues beyond their respective next code tokens
        if (!$outer
            || !$outer->NextCode
            || $outer->NextCode->Index > $eol->Index
            || ((!$outer->EndStatement
                    || $outer->EndStatement->Index <= $outer->NextCode->Index)
                && ($next === $outer
                    || $next->EndStatement->Index <= $next->NextCode->Index))) {
            return null;
        }

        // Return `null` if `$requireAlignedWith` is `true` and there are no
        // tokens between `$outer` and the end of the line where `AlignedWith`
        // is set
        if ($requireAlignedWith
            && !$outer->NextCode
                      ->collect($eol)
                      ->find(fn(Token $t) => (bool) $t->AlignedWith)) {
            return null;
        }

        return $next === $outer
            ? $outer->NextCode
            : $next;
    }

    /**
     * Get the token's last sibling before the end of the line
     *
     * The token returns itself if it satisfies the criteria.
     */
    final public function lastSiblingBeforeNewline(): Token
    {
        $eol = $this->endOfLine();
        $current = $this->ClosedBy ?: $this;
        do {
            $last = $current;
            $current = $current->NextSibling;
        } while ($current
            && $current->Index <= $eol->Index);

        return $last;
    }

    final public function withoutTerminator(): Token
    {
        if ($this->PrevCode
            && ($this->is([\T_SEMICOLON, \T_COMMA, \T_COLON])
                || ($this->Flags & TokenFlag::STATEMENT_TERMINATOR))) {
            return $this->PrevCode;
        }

        return $this;
    }

    final public function withTerminator(): Token
    {
        if ($this->NextCode
            && !($this->is([\T_SEMICOLON, \T_COMMA, \T_COLON])
                || ($this->Flags & TokenFlag::STATEMENT_TERMINATOR))
            && ($this->NextCode->is([\T_SEMICOLON, \T_COMMA, \T_COLON])
                || ($this->NextCode->Flags & TokenFlag::STATEMENT_TERMINATOR))) {
            return $this->NextCode;
        }

        return $this;
    }

    /**
     * @api
     */
    final public function applyBlankLineBefore(bool $withMask = false): void
    {
        $current = $this;
        $prev = $current->Prev;
        /** @var Token|null */
        $last = null;
        while (!$current->hasBlankLineBefore()
            && $prev
            && $this->TypeIndex->Comment[$prev->id]
            && $prev->hasNewlineBefore()
            && (($prev->id === \T_DOC_COMMENT && $prev->hasNewline())
                || ($prev->wasFirstOnLine()
                    && $prev->column <= $this->column))
            && (!$last
                || !$last->Prev
                || (($prev->Flags & TokenFlagMask::COMMENT_TYPE) === ($last->Prev->Flags & TokenFlagMask::COMMENT_TYPE)
                    && !$prev->isMultiLineComment()))) {
            $last = $current;
            $current = $current->Prev;
            $prev = $current->Prev;
        }
        $current->WhitespaceBefore |= WhitespaceType::BLANK;
        if ($withMask) {
            $current->WhitespaceMaskPrev |= WhitespaceType::BLANK;
        }
    }

    final public function effectiveWhitespaceBefore(): int
    {
        return $this->CriticalWhitespaceBefore
            | ($this->Prev->CriticalWhitespaceAfter ?? 0)
            | (($this->WhitespaceBefore
                    | ($this->Prev->WhitespaceAfter ?? 0))
                & ($this->Prev->WhitespaceMaskNext ?? WhitespaceType::ALL)
                & ($this->Prev->CriticalWhitespaceMaskNext ?? WhitespaceType::ALL)
                & $this->WhitespaceMaskPrev
                & $this->CriticalWhitespaceMaskPrev);
    }

    final public function effectiveWhitespaceAfter(): int
    {
        return $this->CriticalWhitespaceAfter
            | ($this->Next->CriticalWhitespaceBefore ?? 0)
            | (($this->WhitespaceAfter
                    | ($this->Next->WhitespaceBefore ?? 0))
                & ($this->Next->WhitespaceMaskPrev ?? WhitespaceType::ALL)
                & ($this->Next->CriticalWhitespaceMaskPrev ?? WhitespaceType::ALL)
                & $this->WhitespaceMaskNext
                & $this->CriticalWhitespaceMaskNext);
    }

    final public function hasNewlineBefore(): bool
    {
        return !!($this->effectiveWhitespaceBefore()
            & (WhitespaceType::LINE | WhitespaceType::BLANK));
    }

    final public function hasNewlineAfter(): bool
    {
        return !!($this->effectiveWhitespaceAfter()
            & (WhitespaceType::LINE | WhitespaceType::BLANK));
    }

    final public function hasBlankLineBefore(): bool
    {
        return !!($this->effectiveWhitespaceBefore() & WhitespaceType::BLANK);
    }

    final public function hasBlankLineAfter(): bool
    {
        return !!($this->effectiveWhitespaceAfter() & WhitespaceType::BLANK);
    }

    /**
     * True if the token contains a newline
     */
    final public function hasNewline(): bool
    {
        return strpos($this->text, "\n") !== false;
    }

    /**
     * True if, between the token and the next code token, there's a newline
     * between tokens
     */
    final public function hasNewlineBeforeNextCode(bool $orInHtml = true): bool
    {
        if ($this->hasNewlineAfter()) {
            return true;
        }
        if (!$this->NextCode || $this->NextCode === $this->Next) {
            return false;
        }
        $current = $this;
        while (true) {
            $current = $current->Next;
            if ($current === $this->NextCode) {
                break;
            }
            if ($current->hasNewlineAfter()) {
                return true;
            }
            if ($orInHtml && (
                $current->id === \T_INLINE_HTML
                || $current === $current->OpenTag
                || $current === $current->CloseTag
            ) && $current->hasNewline()) {
                return true;
            }
        }

        return false;
    }

    final public function isArrayOpenBracket(): bool
    {
        if ($this->id === \T_OPEN_PARENTHESIS) {
            return
                $this->PrevCode
                && $this->PrevCode->id === \T_ARRAY;
        }

        return
            $this->id === \T_OPEN_BRACKET && (
                $this->Expression === $this
                || !$this->PrevCode
                || !$this->PrevCode->isDereferenceableTerminator()
            );
    }

    final public function isDereferenceableTerminator(): bool
    {
        return
            $this->TypeIndex->DereferenceableTerminator[$this->id] || (
                $this->PrevCode
                && $this->PrevCode->id === \T_DOUBLE_COLON
                && $this->TypeIndex->MaybeReserved[$this->id]
            );
    }

    public function isOneLineComment(): bool
    {
        return (bool) ($this->Flags & TokenFlag::ONELINE_COMMENT);
    }

    public function isMultiLineComment(): bool
    {
        return (bool) ($this->Flags & TokenFlag::MULTILINE_COMMENT);
    }

    public function isOperator(): bool
    {
        return $this->is(TokenType::OPERATOR_ALL);
    }

    public function isUnaryOperator(): bool
    {
        return $this->is([
            \T_NOT,
            \T_DOLLAR,
            \T_LOGICAL_NOT,
            ...TokenType::OPERATOR_ERROR_CONTROL,
            ...TokenType::OPERATOR_INCREMENT_DECREMENT
        ]) || (
            $this->is([\T_PLUS, \T_MINUS])
            && $this->inUnaryContext()
        );
    }

    final public function inUnaryContext(): bool
    {
        if ($this->Expression === $this) {
            return true;
        }

        if (!$this->PrevCode) {
            return false;
        }

        return ($this->PrevCode->Flags & TokenFlag::TERNARY_OPERATOR)
            || $this->TypeIndex->UnaryPredecessor[$this->PrevCode->id];
    }

    final public function getIndentDelta(Token $target): TokenIndentDelta
    {
        return TokenIndentDelta::between($this, $target);
    }

    public function indent(): int
    {
        return $this->TagIndent
            + $this->PreIndent
            + $this->Indent
            + $this->HangingIndent
            - $this->Deindent;
    }

    public function renderIndent(bool $softTabs = false): string
    {
        return ($indent = $this->TagIndent + $this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent)
            ? str_repeat($softTabs ? $this->Formatter->SoftTab : $this->Formatter->Tab, $indent)
            : '';
    }

    public function expandedText(): string
    {
        if ($this->ExpandedText === null) {
            return $this->text;
        }

        $tabSize = $this->Formatter->Indentation->TabSize
            ?? $this->Formatter->TabSize;
        return Str::expandLeadingTabs(
            $this->text, $tabSize, !$this->wasFirstOnLine(), $this->column
        );
    }

    public function __toString(): string
    {
        return sprintf(
            'T%d:L%d:%s',
            $this->Index,
            $this->line,
            Str::ellipsize(var_export($this->text, true), 20)
        );
    }
}
