<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token;

use Lkrms\PrettyPHP\Catalog\CommentType;
use Lkrms\PrettyPHP\Catalog\CustomToken;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Exception\InvalidTokenException;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Formatter;
use Salient\Core\Utility\Pcre;
use Closure;

trait NavigableTokenTrait
{
    /**
     * The token's position (0-based) in an array of token objects
     */
    public ?int $Index = null;

    public ?Token $Prev = null;

    public ?Token $Next = null;

    public ?Token $PrevCode = null;

    public ?Token $NextCode = null;

    public ?Token $PrevSibling = null;

    public ?Token $NextSibling = null;

    public ?Token $Statement = null;

    public ?Token $EndStatement = null;

    /**
     * @var Token|false|null
     */
    public $Expression = null;

    public ?Token $EndExpression = null;

    public ?Token $OpenedBy = null;

    public ?Token $ClosedBy = null;

    public ?Token $Parent = null;

    public int $Depth = 0;

    public ?Token $OpenTag = null;

    public ?Token $CloseTag = null;

    public ?Token $String = null;

    public ?Token $StringClosedBy = null;

    public ?Token $Heredoc = null;

    public bool $IsTernaryOperator = false;

    public ?Token $TernaryOperator1 = null;

    public ?Token $TernaryOperator2 = null;

    public ?Token $ChainOpenedBy = null;

    /**
     * True unless the token is a tag, comment, whitespace or inline markup
     *
     * Also `true` if the token is a `T_CLOSE_TAG` that terminates a statement.
     */
    public bool $IsCode = true;

    /**
     * True if the token is a T_NULL
     */
    public bool $IsNull = false;

    /**
     * True if the token is a T_NULL, T_END_ALT_SYNTAX or some other zero-width
     * impostor
     */
    public bool $IsVirtual = false;

    /**
     * @var CommentType::*|null
     */
    public ?string $CommentType = null;

    /**
     * True if the token is a C-style comment where every line starts with "*"
     * or at least one delimiter appears on its own line
     */
    public bool $IsInformalDocComment = false;

    /**
     * True if the token is a T_CLOSE_BRACE or T_CLOSE_TAG that terminates a
     * statement
     */
    public bool $IsStatementTerminator = false;

    /**
     * The original content of the token after expanding tabs if CollectColumn
     * found tabs to expand
     */
    public ?string $ExpandedText = null;

    /**
     * The original content of the token if its content was changed by setText()
     */
    public ?string $OriginalText = null;

    /**
     * The formatter to which the token belongs
     *
     * @readonly
     */
    public Formatter $Formatter;

    /**
     * Indexed token types
     *
     * @readonly
     */
    public TokenTypeIndex $TypeIndex;

    /**
     * @return static[]
     */
    public static function tokenize(string $code, int $flags = 0, Filter ...$filters): array
    {
        return self::filter(parent::tokenize($code, $flags), ...$filters);
    }

    /**
     * Same as tokenize(), but returns lower-cost GenericToken instances
     *
     * @return GenericToken[]
     */
    public static function tokenizeForComparison(string $code, int $flags = 0, Filter ...$filters): array
    {
        return self::filter(GenericToken::tokenize($code, $flags), ...$filters);
    }

    /**
     * @template T of GenericToken
     *
     * @param T[] $tokens
     * @return T[]
     */
    private static function filter(array $tokens, Filter ...$filters): array
    {
        if (!$tokens || !$filters) {
            return $tokens;
        }

        foreach ($filters as $filter) {
            $tokens = $filter->filterTokens($tokens);
        }

        return $tokens;
    }

    /**
     * Tokenize and parse PHP code
     *
     * @return static[]
     */
    public static function parse(string $code, int $flags = 0, ?Formatter $formatter = null, Filter ...$filters): array
    {
        $tokens = static::tokenize($code, $flags, ...$filters);

        if (!$tokens || !$formatter) {
            return $tokens;
        }

        // Pass 1:
        //
        // - link adjacent tokens (set `Prev` and `Next`)
        // - assign formatter and token type index
        // - set `OpenTag`, `CloseTag`

        $idx = $formatter->TokenTypeIndex;

        /** @var (static&Token)|null */
        $prev = null;
        foreach ($tokens as $token) {
            if ($prev) {
                $token->Prev = $prev;
                $prev->Next = $token;
            }

            $token->Formatter = $formatter;
            $token->TypeIndex = $formatter->TokenTypeIndex;

            /**
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
                $token->id === \T_OPEN_TAG ||
                $token->id === \T_OPEN_TAG_WITH_ECHO
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

        // Pass 2:
        //
        // - on PHP < 8.0, convert comments that appear to be PHP >= 8.0
        //   attributes to `T_ATTRIBUTE_COMMENT`
        // - trim the text of each token
        // - add virtual close brackets after alternative syntax blocks
        // - pair open brackets and tags with their counterparts
        // - link siblings, parents and children (set `Parent`, `Depth`,
        //   `PrevCode`, `NextCode`, `PrevSibling`, `NextSibling`)
        // - set `Index`, `IsCode`, `CommentType`, `IsInformalDocComment`,
        //   `IsStatementTerminator`, `OpenedBy`, `ClosedBy`, `String`,
        //   `Heredoc`, `StringClosedBy`

        /** @var (static&Token)[] */
        $linked = [];
        /** @var (static&Token)|null */
        $prev = null;
        $index = 0;

        $keys = array_keys($tokens);
        $count = count($keys);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$keys[$i]];

            if (
                \PHP_VERSION_ID < 80000 &&
                $token->id === \T_COMMENT &&
                substr($token->text, 0, 2) === '#['
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
                    $token->CommentType = '/**';
                } elseif ($token->id === \T_COMMENT) {
                    // "//", "/*" or "#"
                    $token->CommentType = $text[0] === '/'
                        ? substr($text, 0, 2)
                        : $text[0];

                    // Make multi-line C-style comments honourary DocBlocks if:
                    // - every line starts with "*", or
                    // - at least one delimiter appears on its own line
                    $token->IsInformalDocComment =
                        $token->CommentType === CommentType::C &&
                        strpos($text, "\n") !== false && (
                            // Every line starts with "*"
                            !Pcre::match('/\n\h*+(?!\*)\S/', $text) ||
                            // The first delimiter is followed by a newline
                            !Pcre::match('/^\/\*++(\h++|(?!\*))\S/', $text) ||
                            // The last delimiter is preceded by a newline
                            !Pcre::match('/\S((?<!\*)|\h++)\*++\/$/', $text)
                        );
                }
            }

            if ((
                $idx->AltSyntaxContinue[$token->id] ||
                $idx->AltSyntaxEnd[$token->id]
            ) && $prev->id !== \T_END_ALT_SYNTAX) {
                $opener = $prev->Parent;
                if (($opener &&
                    $opener->id === \T_COLON &&
                    ($idx->AltSyntaxEnd[$token->id] ||
                        ($idx->AltSyntaxContinueWithExpression[$token->id] &&
                            $token->nextSimpleSibling(2)->id === \T_COLON) ||
                        ($idx->AltSyntaxContinueWithoutExpression[$token->id] &&
                            $token->nextSimpleSibling()->id === \T_COLON))) ||
                    ($prev->id === \T_COLON &&
                        $prev->isColonAltSyntaxDelimiter())) {
                    $i--;
                    $virtual = new static(\T_END_ALT_SYNTAX, '');
                    $virtual->IsVirtual = true;
                    $virtual->Prev = $prev;
                    $virtual->Next = $token;
                    $virtual->Formatter = $formatter;
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
                    $t->id === \T_COMMENT ||
                    $t->id === \T_DOC_COMMENT ||
                    $t->id === \T_ATTRIBUTE_COMMENT
                ) {
                    $t = $t->Prev;
                }

                if (
                    $t !== $token->OpenTag &&
                    $t->id !== \T_COLON &&
                    $t->id !== \T_SEMICOLON &&
                    $t->id !== \T_OPEN_BRACE &&
                    ($t->id !== \T_CLOSE_BRACE || !$t->IsStatementTerminator)
                ) {
                    $token->IsStatementTerminator = true;
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
                $idx->OpenBracket[$prev->id] ||
                ($prev->id === \T_COLON && $prev->isColonAltSyntaxDelimiter())
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
                $idx->StringDelimiter[$token->id] &&
                $token->String &&
                $token->Parent === $token->String->Parent && (
                    ($token->String->id === \T_START_HEREDOC && $token->id === \T_END_HEREDOC) ||
                    ($token->String->id !== \T_START_HEREDOC && $token->String->id === $token->id)
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
                    $token->id !== \T_CLOSE_BRACE ||
                    !$token->isStructuralBrace(false)
                ) {
                    $prev = $token;
                    continue;
                }

                $_prev = $token->prevSiblingOf(\T_FUNCTION, \T_CLASS);
                if (
                    !$_prev->IsNull &&
                    $_prev->nextSiblingOf(\T_OPEN_BRACE)->ClosedBy === $token
                ) {
                    $_next = $_prev->NextSibling;
                    if (
                        $_next->id === \T_OPEN_PARENTHESIS ||
                        $_next->id === \T_OPEN_BRACE ||
                        $_next->id === \T_EXTENDS ||
                        $_next->id === \T_IMPLEMENTS
                    ) {
                        $prev = $token;
                        continue;
                    }
                }

                $token->IsStatementTerminator = true;

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
                    $token->PrevSibling &&
                    !$token->PrevSibling->NextSibling
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

        // Pass 3: resolve statements

        $endStatementOffset = 0;
        $token = reset($linked);
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
                break;
            }

            // The following tokens are regarded as statement terminators:
            //
            // - `T_SEMICOLON`, or `T_CLOSE_BRACE` / `T_CLOSE_TAG` where
            //   `$IsStatementTerminator` is `true`, unless the next token
            //   continues an open control structure
            // - `T_COLON` after a switch case or a label
            // - The last token between brackets other than structural braces
            // - `T_COMMA`:
            //   - between parentheses and square brackets, e.g. in argument
            //     lists, arrays, `for` expressions
            //   - between non-structural braces, e.g. in `match` expressions

            if ($token->id === \T_SEMICOLON || $token->IsStatementTerminator) {
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
                    $token->id === \T_CLOSE_BRACE &&
                    !$token->isStructuralBrace(false)
                )
            ) {
                $endStatementOffset = 2;
            }

            if ($token->id === \T_COMMA) {
                if (($parent = $token->Parent) && (
                    $idx->OpenBracketExceptBrace[$parent->id] || (
                        $parent->id === \T_OPEN_BRACE &&
                        !$parent->isStructuralBrace(false)
                    )
                )) {
                    $endStatementOffset = 1;
                }
                continue;
            }
        }

        // Pass 4:
        //
        // - resolve expressions
        // - identify ternary operators and set `IsTernaryOperator`,
        //   `TernaryOperator1`, `TernaryOperator2`
        // - identify method chains and set `ChainOpenedBy`

        $endExpressionOffsets = [];
        $token = reset($linked);
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
                break;
            }

            if (
                $token->id === \T_QUESTION &&
                $token->getSubType() === TokenSubType::QUESTION_TERNARY_OPERATOR
            ) {
                $current = $token;
                $count = 0;
                while (($current = $current->NextSibling) &&
                        $token->EndStatement !== ($current->ClosedBy ?: $current)) {
                    if ($current->IsTernaryOperator) {
                        continue;
                    }
                    if ($current->id === \T_QUESTION &&
                        $current->getSubType() ===
                            TokenSubType::QUESTION_TERNARY_OPERATOR) {
                        $count++;
                        continue;
                    }
                    if (!($current->id === \T_COLON &&
                        $current->getSubType() ===
                            TokenSubType::COLON_TERNARY_OPERATOR)) {
                        continue;
                    }
                    if ($count--) {
                        continue;
                    }
                    $current->IsTernaryOperator = $token->IsTernaryOperator = true;
                    $current->TernaryOperator1 = $token->TernaryOperator1 = $token;
                    $current->TernaryOperator2 = $token->TernaryOperator2 = $current;
                    break;
                }
            }

            if ($idx->Chain[$token->id] && !$token->ChainOpenedBy) {
                $token->ChainOpenedBy = $current = $token;
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

            if ($idx->ExpressionTerminator[$token->id] ||
                    $token->IsStatementTerminator ||
                    ($token->id === \T_COLON && $token->isColonStatementDelimiter()) ||
                    ($token->id === \T_CLOSE_BRACE &&
                        (!$token->isStructuralBrace() || $token->isMatchBrace())) ||
                    $token->IsTernaryOperator) {
                // Expression terminators don't form part of the expression
                $token->Expression = false;
                if ($token->PrevCode) {
                    $endExpressionOffsets = [2];
                }
                continue;
            }

            if ($token->id === \T_COMMA) {
                $parent = $token->parent();
                if ($parent->is([\T_OPEN_BRACKET, \T_OPEN_PARENTHESIS, \T_ATTRIBUTE]) ||
                    ($parent->id === \T_OPEN_BRACE &&
                        (!$parent->isStructuralBrace() || $token->isMatchDelimiter()))) {
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

        return $linked;
    }

    /**
     * True if the token is a brace that delimits a code block
     *
     * Returns `false` for braces in:
     * - expressions (e.g. `$object->{$property}`)
     * - strings (e.g. `"{$object->property}"`)
     * - alias/import statements (e.g. `use A\{B, C}`)
     *
     * Returns `true` for braces around trait adaptations, and for `match`
     * expression braces if `$orMatch` is `true`.
     */
    final public function isStructuralBrace(bool $orMatch = true): bool
    {
        /** @var Token */
        $current = $this->OpenedBy ?: $this;

        // Exclude T_CURLY_OPEN and T_DOLLAR_OPEN_CURLY_BRACES
        if ($current->id !== \T_OPEN_BRACE) {
            return false;
        }

        /** @var Token|null */
        $prev = $current->PrevSibling->PrevSibling ?? null;
        if ($prev && $prev->id === \T_MATCH) {
            return $orMatch;
        }

        $lastInner = $current->ClosedBy->PrevCode;

        // Braces cannot be empty in expression (dereferencing) contexts, but
        // trait adaptation braces can be
        return $lastInner === $current ||                                            // `{}`
            $lastInner->is([\T_COLON, \T_SEMICOLON]) ||                              // `{ statement; }`
            $lastInner->IsStatementTerminator ||                                     /* `{ statement ?>...<?php }` */
            ($lastInner->id === \T_CLOSE_BRACE && $lastInner->isStructuralBrace());  // `{ { statement; } }`
    }

    /**
     * True if the token is a T_WHILE that belongs to a do ... while structure
     */
    final public function isWhileAfterDo(): bool
    {
        /** @var Token $this */
        if (
            $this->id !== \T_WHILE ||
            !$this->PrevSibling ||
            !$this->PrevSibling->PrevSibling
        ) {
            return false;
        }

        // Test for enclosed and unenclosed bodies, e.g.
        // - `do { ... } while ();`
        // - `do statement; while ();`

        if ($this->PrevSibling->PrevSibling->id === \T_DO) {
            return true;
        }

        // Starting from the previous sibling because `do` immediately before
        // `while` cannot be part of the same structure, look for a previous
        // `T_DO` the token could form a control structure with
        $do = $this->PrevSibling->prevSiblingOf(\T_DO)->orNull();
        if (!$do) {
            return false;
        }
        // Now look for its `T_WHILE` counterpart, starting from the first token
        // it could be and allowing for nested unenclosed `T_WHILE` loops, e.g.
        // `do while () while (); while ();`
        $tokens = $do->NextSibling->NextSibling->collectSiblings($this);
        foreach ($tokens as $token) {
            if (
                $token->id === \T_WHILE &&
                $token->PrevSibling->PrevSibling->id !== \T_WHILE
            ) {
                return $token === $this;
            }
        }
        return false;
    }

    /**
     * Get a new T_NULL token
     *
     * @return Token
     */
    public function null()
    {
        $token = new static(\T_NULL, '');
        $token->IsCode = false;
        $token->IsNull = true;
        $token->IsVirtual = true;
        if (isset($this->TypeIndex)) {
            $token->TypeIndex = $this->TypeIndex;
        }
        return $token;
    }

    /**
     * Get the token if it is not null, otherwise get a fallback token
     *
     * @param Token|(Closure(): Token) $token
     * @return Token
     */
    public function or($token)
    {
        if (!$this->IsNull) {
            return $this;
        }
        if ($token instanceof Closure) {
            return $token();
        }
        return $token;
    }

    /**
     * Get the token if it is not null
     *
     * Returns `null` if the token is a null token.
     *
     * @return $this|null
     */
    public function orNull()
    {
        if ($this->IsNull) {
            return null;
        }
        return $this;
    }

    /**
     * Get the token if it is not null, otherwise throw an InvalidTokenException
     *
     * @return $this|never
     */
    public function orThrow()
    {
        if ($this->IsNull) {
            throw new InvalidTokenException($this);
        }
        return $this;
    }

    public function getTokenName(): ?string
    {
        return parent::getTokenName() ?: CustomToken::toName($this->id);
    }

    /**
     * Update the content of the token, setting OriginalText if needed
     *
     * @return $this
     */
    final public function setText(string $text)
    {
        if ($this->text !== $text) {
            if ($this->OriginalText === null) {
                $this->OriginalText = $this->text;
            }
            $this->text = $text;
        }
        return $this;
    }

    /**
     * Get the previous token that is one of the types in an index
     *
     * @param array<int,bool> $index
     * @return Token
     */
    final public function prevFrom(array $index)
    {
        $t = $this;
        while ($t = $t->Prev) {
            if ($index[$t->id]) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the next token that is one of the types in an index
     *
     * @param array<int,bool> $index
     * @return Token
     */
    final public function nextFrom(array $index)
    {
        $t = $this;
        while ($t = $t->Next) {
            if ($index[$t->id]) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the previous sibling that is one of the types in an index
     *
     * @param array<int,bool> $index
     * @return Token
     */
    final public function prevSiblingFrom(array $index)
    {
        $t = $this;
        while ($t = $t->PrevSibling) {
            if ($index[$t->id]) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the next sibling that is one of the types in an index
     *
     * @param array<int,bool> $index
     * @return Token
     */
    final public function nextSiblingFrom(array $index)
    {
        $t = $this;
        while ($t = $t->NextSibling) {
            if ($index[$t->id]) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the previous token that is one of the listed types
     *
     * @return Token
     */
    final public function prevOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this;
        while ($t = $t->Prev) {
            if ($t->is($types)) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the next token that is one of the listed types
     *
     * @return Token
     */
    final public function nextOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this;
        while ($t = $t->Next) {
            if ($t->is($types)) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the previous sibling that is one of the listed types
     *
     * @return Token
     */
    final public function prevSiblingOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this;
        while ($t = $t->PrevSibling) {
            if ($t->is($types)) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the next sibling that is one of the listed types
     *
     * @return Token
     */
    final public function nextSiblingOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this;
        while ($t = $t->NextSibling) {
            if ($t->is($types)) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Skip to the next sibling that is not one of the listed types
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @return Token
     */
    final public function skipSiblingsOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this->IsCode ? $this : $this->NextCode;
        while ($t && $t->is($types)) {
            $t = $t->NextSibling;
        }
        return $t ?: $this->null();
    }

    /**
     * Skip to the previous sibling that is not one of the listed types
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @return Token
     */
    final public function skipPrevSiblingsOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this->IsCode ? $this : $this->PrevCode;
        while ($t && $t->is($types)) {
            $t = $t->PrevSibling;
        }
        return $t ?: $this->null();
    }

    /**
     * Get the first reachable token
     *
     * @return Token
     */
    final public function first()
    {
        $current = $this;
        while ($current->Parent) {
            $current = $current->Parent;
        }
        while ($current->Prev) {
            $current = $current->PrevSibling ?: $current->Prev;
        }
        return $current;
    }

    /**
     * Get the last reachable token
     *
     * @return Token
     */
    final public function last()
    {
        $current = $this;
        while ($current->Parent) {
            $current = $current->Parent;
        }
        while ($current->Next) {
            $current = $current->NextSibling ?: $current->Next;
        }
        return $current;
    }

    /**
     * Get the token at the beginning of the token's original line
     *
     * @return Token
     */
    final public function startOfOriginalLine()
    {
        $current = $this;
        while (($current->Prev->line ?? null) === $this->line) {
            $current = $current->Prev;
        }
        return $current;
    }

    /**
     * Get the token at the end of the token's original line
     *
     * @return Token
     */
    final public function endOfOriginalLine()
    {
        $current = $this;
        while (($current->Next->line ?? null) === $this->line) {
            $current = $current->Next;
        }
        return $current;
    }

    /**
     * Get the next sibling via token traversal, without accounting for PHP's
     * alternative syntax
     *
     * @return Token
     */
    final public function nextSimpleSibling(int $offset = 1)
    {
        $depth = 0;
        $t = $this;
        while ($t->Next) {
            if ($this->TypeIndex->OpenBracket[$t->id]) {
                $depth++;
            } elseif ($this->TypeIndex->CloseBracket[$t->id]) {
                $depth--;
            }
            $t = $t->Next;
            if (!$depth) {
                $offset--;
                if (!$offset) {
                    return $t;
                }
            }
        }
        return $this->null();
    }

    /**
     * Throw an InvalidTokenException
     *
     * @return never
     */
    final protected function throw(): void
    {
        throw new InvalidTokenException($this);
    }
}
