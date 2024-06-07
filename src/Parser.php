<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Token\Token;
use Salient\Core\Concern\HasImmutableProperties;
use Salient\Utility\Regex;

final class Parser
{
    use HasImmutableProperties;

    private Formatter $Formatter;

    public function __construct(Formatter $formatter)
    {
        $this->Formatter = $formatter;
    }

    /**
     * Get an instance with the given formatter
     *
     * @return static
     */
    public function withFormatter(Formatter $formatter): self
    {
        return $this->withPropertyValue('Formatter', $formatter);
    }

    /**
     * Tokenize, filter and parse PHP code
     *
     * @return Token[]
     */
    public function parse(
        string $code,
        Filter ...$filters
    ): array {
        $tokens = Token::tokenize($code, \TOKEN_PARSE, ...$filters);

        if (!$tokens) {
            return $tokens;
        }

        $tokens = $this->linkTokens($tokens)
                       ->buildHierarchy($tokens);
        return $this->parseStatements($tokens)
                    ->parseExpressions($tokens);
    }

    /**
     * Pass 1: link adjacent tokens
     *
     * Token properties set:
     *
     * - `Prev`
     * - `Next`
     * - `OpenTag`
     * - `CloseTag`
     * - `Formatter`
     * - `TypeIndex`
     *
     * @param Token[] $tokens
     * @return $this
     */
    private function linkTokens(array $tokens)
    {
        /** @var Token|null */
        $prev = null;

        foreach ($tokens as $token) {
            $token->Formatter = $this->Formatter;
            $token->TypeIndex = $this->Formatter->TokenTypeIndex;

            if ($prev) {
                $token->Prev = $prev;
                $prev->Next = $token;
            }

            /*
             * ```php
             * <!-- markup -->  // OpenTag = null,   CloseTag = null
             * <?php            // OpenTag = itself, CloseTag = Token
             * $foo = 'bar';    // OpenTag = Token,  CloseTag = Token
             * ?>               // OpenTag = Token,  CloseTag = itself
             * <!-- markup -->  // OpenTag = null,   CloseTag = null
             * <?php            // OpenTag = itself, CloseTag = null
             * $foo = 'bar';    // OpenTag = Token,  CloseTag = null
             * ```
             */
            if (
                $token->id === \T_OPEN_TAG
                || $token->id === \T_OPEN_TAG_WITH_ECHO
            ) {
                $token->OpenTag = $token;
                $prev = $token;
                continue;
            }

            if (!$prev || !$prev->OpenTag || $prev->CloseTag) {
                $prev = $token;
                continue;
            }

            $token->OpenTag = $prev->OpenTag;
            $token->CloseTag = &$token->OpenTag->CloseTag;

            if ($token->id === \T_CLOSE_TAG) {
                $token->OpenTag->CloseTag = $token;
            }

            $prev = $token;
        }

        return $this;
    }

    /**
     * Pass 2: link brackets, siblings and parents
     *
     * - on PHP < 8.0, convert comments that appear to be PHP >= 8.0 attributes
     *   to `T_ATTRIBUTE_COMMENT`
     * - trim the text of each token
     * - add virtual close brackets after alternative syntax blocks
     * - pair open brackets and tags with their counterparts
     *
     * Token properties set:
     * - `Index`
     * - `PrevCode`
     * - `NextCode`
     * - `PrevSibling`
     * - `NextSibling`
     * - `OpenedBy`
     * - `ClosedBy`
     * - `Parent`
     * - `Depth`
     * - `String`
     * - `StringClosedBy`
     * - `Heredoc`
     * - `IsCode`
     *
     * @param Token[] $tokens
     * @return Token[]
     */
    private function buildHierarchy(array $tokens): array
    {
        $idx = $this->Formatter->TokenTypeIndex;

        /** @var Token[] */
        $linked = [];
        /** @var Token|null */
        $prev = null;
        $index = 0;

        $keys = array_keys($tokens);
        $count = count($keys);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$keys[$i]];

            if (
                \PHP_VERSION_ID < 80000
                && $token->id === \T_COMMENT
                && substr($token->text, 0, 2) === '#['
            ) {
                $token->id = \T_ATTRIBUTE_COMMENT;
            }

            $text = $token->text;
            if ($idx->Trim[$token->id]) {
                if ($idx->DoNotModifyLeft[$token->id]) {
                    $text = rtrim($text);
                } elseif ($idx->DoNotModifyRight[$token->id]) {
                    $text = ltrim($text);
                } else {
                    $text = trim($text);
                }
                if ($text !== $token->text) {
                    $token->setText($text);
                }
            }

            if ($idx->NotCode[$token->id]) {
                $token->IsCode = false;

                if ($token->id === \T_DOC_COMMENT) {
                    // @phpstan-ignore-next-line
                    $token->Flags |= TokenFlag::DOC_COMMENT;
                } elseif ($token->id === \T_COMMENT) {
                    // "//", "/*" or "#"
                    // @phpstan-ignore-next-line
                    $token->Flags |= (
                        $text[0] === '/'
                            ? ($text[1] === '/' ? TokenFlag::CPP_COMMENT : TokenFlag::C_COMMENT)
                            : TokenFlag::SHELL_COMMENT
                    );

                    // Make multi-line C-style comments honourary DocBlocks if:
                    // - every line starts with "*", or
                    // - at least one delimiter appears on its own line
                    if (($token->Flags & TokenFlag::C_COMMENT) === TokenFlag::C_COMMENT
                            && strpos($text, "\n") !== false
                            && (
                                // Every line starts with "*"
                                !Regex::match('/\n\h*+(?!\*)\S/', $text)
                                // The first delimiter is followed by a newline
                                || !Regex::match('/^\/\*++(\h++|(?!\*))\S/', $text)
                                // The last delimiter is preceded by a newline
                                || !Regex::match('/\S((?<!\*)|\h++)\*++\/$/', $text)
                            )) {
                        // @phpstan-ignore-next-line
                        $token->Flags |= TokenFlag::INFORMAL_DOC_COMMENT;
                    }
                }
            }

            if ((
                $idx->AltSyntaxContinue[$token->id]
                || $idx->AltSyntaxEnd[$token->id]
            ) && $prev->id !== \T_END_ALT_SYNTAX) {
                $opener = $prev->Parent;
                if (($opener
                    && $opener->id === \T_COLON
                    && ($idx->AltSyntaxEnd[$token->id]
                        || ($idx->AltSyntaxContinueWithExpression[$token->id]
                            && $token->nextSimpleSibling(2)->id === \T_COLON)
                        || ($idx->AltSyntaxContinueWithoutExpression[$token->id]
                            && $token->nextSimpleSibling()->id === \T_COLON)))
                    || ($prev->id === \T_COLON
                        && $prev->isColonAltSyntaxDelimiter())) {
                    $i--;
                    $virtual = new Token(\T_END_ALT_SYNTAX, '');
                    $virtual->IsVirtual = true;
                    $virtual->Prev = $prev;
                    $virtual->Next = $token;
                    $virtual->Formatter = $this->Formatter;
                    $virtual->TypeIndex = $idx;
                    $virtual->OpenTag = $token->OpenTag;
                    $virtual->CloseTag = &$virtual->OpenTag->CloseTag;
                    $prev->Next = $virtual;
                    $token->Prev = $virtual;
                    $token = $virtual;
                }
            }

            $linked[$index] = $token;
            $token->Index = $index++;

            if (!$prev) {
                $prev = $token;
                continue;
            }

            // Determine whether or not a close tag is also a statement
            // terminator and should therefore be regarded as a code token
            if ($token->id === \T_CLOSE_TAG) {
                $t = $prev;
                while (
                    $t->id === \T_COMMENT
                    || $t->id === \T_DOC_COMMENT
                    || $t->id === \T_ATTRIBUTE_COMMENT
                ) {
                    $t = $t->Prev;
                }

                if (
                    $t !== $token->OpenTag
                    && $t->id !== \T_COLON
                    && $t->id !== \T_SEMICOLON
                    && $t->id !== \T_OPEN_BRACE
                    && (
                        $t->id !== \T_CLOSE_BRACE
                        || !($t->Flags & TokenFlag::STATEMENT_TERMINATOR)
                    )
                ) {
                    // @phpstan-ignore-next-line
                    $token->Flags |= TokenFlag::STATEMENT_TERMINATOR;
                    $token->IsCode = true;
                }
            }

            $token->PrevCode = $prev->IsCode ? $prev : $prev->PrevCode;
            if ($token->IsCode) {
                $prev->NextCode = $token;
            } else {
                $token->NextCode = &$prev->NextCode;
            }

            $token->Depth = $prev->Depth;
            $delta = 0;
            if (
                $idx->OpenBracket[$prev->id]
                || ($prev->id === \T_COLON && $prev->isColonAltSyntaxDelimiter())
            ) {
                $token->Parent = $prev;
                $token->Depth++;
                $delta++;
            } elseif ($idx->CloseBracketOrEndAltSyntax[$prev->id]) {
                $token->Parent = $prev->Parent;
                $token->Depth--;
                $delta--;
            } else {
                $token->Parent = $prev->Parent;
            }

            $token->String = $prev->String;
            $token->Heredoc = $prev->Heredoc;
            if ($idx->StringDelimiter[$prev->id]) {
                if ($prev->String && $prev->String->StringClosedBy === $prev) {
                    $token->String = $prev->String->String;
                    if ($prev->id === \T_END_HEREDOC) {
                        $token->Heredoc = $prev->Heredoc->Heredoc;
                    }
                } else {
                    $token->String = $prev;
                    if ($prev->id === \T_START_HEREDOC) {
                        $token->Heredoc = $prev;
                    }
                }
            }

            if (
                $idx->StringDelimiter[$token->id]
                && $token->String
                && $token->Parent === $token->String->Parent
                && (
                    ($token->String->id === \T_START_HEREDOC && $token->id === \T_END_HEREDOC)
                    || ($token->String->id !== \T_START_HEREDOC && $token->String->id === $token->id)
                )
            ) {
                $token->String->StringClosedBy = $token;
            }

            if ($idx->CloseBracketOrEndAltSyntax[$token->id]) {
                $opener = $token->Parent;
                $opener->ClosedBy = $token;
                $token->OpenedBy = $opener;
                $token->PrevSibling = &$opener->PrevSibling;
                $token->NextSibling = &$opener->NextSibling;
                $token->Parent = &$opener->Parent;
                $token->Statement = &$opener->Statement;
                $token->EndStatement = &$opener->EndStatement;

                // Treat `$token` as a statement terminator if it's a structural
                // `T_CLOSE_BRACE` that doesn't enclose an anonymous function or
                // class
                if (
                    $token->id !== \T_CLOSE_BRACE
                    || !$token->isStructuralBrace()
                ) {
                    $prev = $token;
                    continue;
                }

                $_prev = $token->prevSiblingOf(\T_FUNCTION, \T_CLASS);
                if (
                    !$_prev->IsNull
                    && $_prev->nextSiblingOf(\T_OPEN_BRACE)->ClosedBy === $token
                ) {
                    $_next = $_prev->NextSibling;
                    if (
                        $_next->id === \T_OPEN_PARENTHESIS
                        || $_next->id === \T_OPEN_BRACE
                        || $_next->id === \T_EXTENDS
                        || $_next->id === \T_IMPLEMENTS
                    ) {
                        $prev = $token;
                        continue;
                    }
                }
                // @phpstan-ignore-next-line
                $token->Flags |= TokenFlag::STATEMENT_TERMINATOR;

                $prev = $token;
                continue;
            }

            // If $token continues the previous context ($delta == 0) or is the
            // first token after a close bracket ($delta < 0), set
            // $token->PrevSibling
            if ($delta <= 0 && $token->PrevCode) {
                $prevCode = $token->PrevCode->OpenedBy ?: $token->PrevCode;
                if ($prevCode->Parent === $token->Parent) {
                    $token->PrevSibling = $prevCode;
                }
            }

            // Then, if there are gaps between siblings, fill them in
            if ($token->IsCode) {
                if (
                    $token->PrevSibling
                    && !$token->PrevSibling->NextSibling
                ) {
                    $t = $token;
                    do {
                        $t = $t->Prev->OpenedBy ?: $t->Prev;
                        $t->NextSibling = $token;
                    } while ($t !== $token->PrevSibling && $t->Prev);
                } elseif (!$token->PrevSibling) {
                    $t = $token->Prev;
                    while ($t && $t->Parent === $token->Parent) {
                        $t->NextSibling = $token;
                        $t = $t->Prev;
                    }
                }
            }

            $prev = $token;
        }

        return $linked;
    }

    /**
     * Pass 3: resolve statements
     *
     * @param Token[] $tokens
     * @return $this
     */
    private function parseStatements(array $tokens)
    {
        $idx = $this->Formatter->TokenTypeIndex;

        $endStatementOffset = 0;
        $token = reset($tokens);
        while (true) {
            // If `$token` or its predecessor is a statement terminator, set the
            // `Statement` and `EndStatement` of siblings between the end of the
            // previous statement (or the start of the context, if there is no
            // previous statement) and `$token`
            if ($endStatementOffset) {
                $end = $token;
                while (--$endStatementOffset) {
                    $end = $end->PrevCode;
                }

                // Skip empty brackets
                if ($end->ClosedBy && $end->NextCode === $end->ClosedBy) {
                    continue;
                }

                $current = $end->OpenedBy ?: $end;
                while ($current && !$current->EndStatement) {
                    $current->EndStatement = $end;
                    $start = $current;
                    $current = $current->PrevSibling;
                }

                $start ??= $token;
                $current = $start;
                do {
                    $current->Statement = $start;
                    $current = $current->NextSibling;
                } while ($current && $current->EndStatement === $end);
            }

            $token = $token->NextCode;

            if (!$token) {
                return $this;
            }

            // The following tokens are regarded as statement terminators:
            //
            // - `T_SEMICOLON`, or `T_CLOSE_BRACE` / `T_CLOSE_TAG` where the
            //   `STATEMENT_TERMINATOR` flag is set, unless the next token
            //   continues an open control structure
            // - `T_COLON` after a switch case or a label
            // - The last token between brackets other than structural braces
            // - `T_COMMA`:
            //   - between parentheses and square brackets, e.g. in argument
            //     lists, arrays, `for` expressions
            //   - between non-structural braces, e.g. in `match` expressions

            if (
                $token->id === \T_SEMICOLON
                || ($token->Flags & TokenFlag::STATEMENT_TERMINATOR)
            ) {
                if ($next = $token->NextCode) {
                    if ($idx->ContinuesControlStructure[$next->id]) {
                        continue;
                    }
                    if ($next->id === \T_WHILE && $next->isWhileAfterDo()) {
                        continue;
                    }
                }
                $endStatementOffset = 1;
                continue;
            }

            if ($token->id === \T_COLON) {
                if ($token->isColonStatementDelimiter()) {
                    $endStatementOffset = 1;
                }
                continue;
            }

            if (
                $idx->CloseBracketExceptBrace[$token->id] || (
                    $token->id === \T_CLOSE_BRACE
                    && !$token->isStructuralBrace()
                )
            ) {
                $endStatementOffset = 2;
            }

            if ($token->id === \T_COMMA) {
                if (($parent = $token->Parent) && (
                    $idx->OpenBracketExceptBrace[$parent->id] || (
                        $parent->id === \T_OPEN_BRACE
                        && !$parent->isStructuralBrace()
                    )
                )) {
                    $endStatementOffset = 1;
                }
                continue;
            }
        }
    }

    /**
     * Pass 4: resolve expressions
     *
     * Token properties set:
     *
     * - `OtherTernaryOperator`
     * - `ChainOpenedBy`
     *
     * @param Token[] $tokens
     * @return Token[]
     */
    private function parseExpressions(array $tokens): array
    {
        $idx = $this->Formatter->TokenTypeIndex;

        $endExpressionOffsets = [];
        $token = reset($tokens);
        while (true) {
            if ($endExpressionOffsets) {
                foreach ($endExpressionOffsets as $endExpressionOffset) {
                    $end = $token;
                    while (--$endExpressionOffset) {
                        $end = $end->PrevCode;
                    }

                    // Skip empty brackets
                    if ($end->ClosedBy && $end->NextCode === $end->ClosedBy) {
                        continue;
                    }

                    unset($start);

                    $current = $end->OpenedBy ?: $end;
                    while ($current && !$current->EndExpression) {
                        $current->EndExpression = $end;
                        if ($current->ClosedBy) {
                            $current->ClosedBy->EndExpression = $end;
                        }
                        if ($current->Expression === false) {
                            break;
                        }
                        $start = $current;
                        $current = $current->PrevSibling;
                    }

                    if (!isset($start)) {
                        continue;
                    }

                    $current = $start;
                    do {
                        $current->Expression = $start;
                        if ($current->ClosedBy) {
                            $current->ClosedBy->Expression = $start;
                        }
                        $current = $current->NextSibling;
                    } while ($current && $current->EndExpression === $end);
                }

                $endExpressionOffsets = [];
            }

            $token = $token->NextCode;

            if (!$token) {
                return $tokens;
            }

            if (
                $token->id === \T_QUESTION
                && $token->getSubType() === TokenSubType::QUESTION_TERNARY_OPERATOR
            ) {
                $current = $token;
                $count = 0;
                while (($current = $current->NextSibling)
                        && $token->EndStatement !== ($current->ClosedBy ?: $current)) {
                    if ($current->Flags & TokenFlag::TERNARY_OPERATOR) {
                        continue;
                    }
                    if ($current->id === \T_QUESTION
                        && $current->getSubType()
                            === TokenSubType::QUESTION_TERNARY_OPERATOR) {
                        $count++;
                        continue;
                    }
                    if (!($current->id === \T_COLON
                        && $current->getSubType()
                            === TokenSubType::COLON_TERNARY_OPERATOR)) {
                        continue;
                    }
                    if ($count--) {
                        continue;
                    }
                    // @phpstan-ignore-next-line
                    $current->Flags |= TokenFlag::TERNARY_OPERATOR;
                    // @phpstan-ignore-next-line
                    $token->Flags |= TokenFlag::TERNARY_OPERATOR;
                    $current->OtherTernaryOperator = $token;
                    $token->OtherTernaryOperator = $current;
                    break;
                }
            }

            if ($idx->Chain[$token->id] && !$token->ChainOpenedBy) {
                $token->ChainOpenedBy = $token;
                $current = $token;
                while (($current = $current->NextSibling) && $idx->ChainPart[$current->id]) {
                    if ($idx->Chain[$current->id]) {
                        $current->ChainOpenedBy = $token;
                    }
                }
            }

            if ($token->id === \T_CLOSE_BRACE && $token->isStructuralBrace()) {
                $endExpressionOffsets = [2, 1];
                continue;
            }

            if (
                $idx->ExpressionTerminator[$token->id]
                || ($token->Flags & TokenFlag::STATEMENT_TERMINATOR)
                || ($token->Flags & TokenFlag::TERNARY_OPERATOR)
                || ($token->id === \T_COLON && $token->isColonStatementDelimiter())
                || ($token->id === \T_CLOSE_BRACE && !$token->isStructuralBrace())
            ) {
                // Expression terminators don't form part of the expression
                $token->Expression = false;
                if ($token->PrevCode) {
                    $endExpressionOffsets = [2];
                }
                continue;
            }

            if ($token->id === \T_COMMA) {
                $parent = $token->Parent;
                if ($parent && (
                    $idx->OpenBracketExceptBrace[$parent->id]
                    || ($parent->id === \T_OPEN_BRACE && !$parent->isStructuralBrace())
                )) {
                    $token->Expression = false;
                    $endExpressionOffsets = [2];
                }
                continue;
            }

            // Catch the last global expression
            if (!$token->Next) {
                $endExpressionOffsets = [1];
            }
        }
    }
}
