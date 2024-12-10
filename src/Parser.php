<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenSubId;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Internal\Document;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\HasMutator;
use Salient\Utility\Exception\ShouldNotHappenException;
use Salient\Utility\Regex;

final class Parser implements Immutable
{
    use HasMutator;

    private const DECLARATION_MAP = [
        \T_CASE => Type::_CASE,
        \T_CLASS => Type::_CLASS,
        \T_CONST => Type::_CONST,
        \T_DECLARE => Type::_DECLARE,
        \T_ENUM => Type::_ENUM,
        \T_FUNCTION => Type::_FUNCTION,
        \T_INTERFACE => Type::_INTERFACE,
        \T_NAMESPACE => Type::_NAMESPACE,
        \T_TRAIT => Type::_TRAIT,
        \T_USE => Type::_USE,
    ];

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
        return $this->with('Formatter', $formatter);
    }

    /**
     * Tokenize, filter and parse PHP code
     */
    public function parse(
        string $code,
        Filter ...$filters
    ): Document {
        $tokens = Token::tokenize($code, \TOKEN_PARSE, ...$filters);

        if (!$tokens) {
            return new Document();
        }

        $this->linkTokens($tokens);
        $this->buildHierarchy($tokens, $tokensById, $scopes);
        $this->parseStatements($scopes, $statements);
        $this->parseExpressions($statements, $declarations, $declarationsByType);

        return new Document(
            $tokens,
            $tokensById,
            $statements,
            $declarations,
            $declarationsByType,
        );
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
     * - `Idx`
     *
     * @param Token[] $tokens
     */
    private function linkTokens(array $tokens): void
    {
        /** @var Token|null */
        $prev = null;

        foreach ($tokens as $token) {
            $token->Formatter = $this->Formatter;
            $token->Idx = $this->Formatter->TokenIndex;

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
     *
     * - `index`
     * - `PrevCode`
     * - `NextCode`
     * - `PrevSibling`
     * - `NextSibling`
     * - `OpenBracket`
     * - `CloseBracket`
     * - `Parent`
     * - `Depth`
     * - `String`
     * - `Heredoc`
     * - `Data[TokenData::STRING_CLOSED_BY]`
     *
     * @param non-empty-list<Token> $tokens
     * @param-out non-empty-list<Token> $tokens
     * @param array<int,array<int,Token>>|null $tokensById
     * @param-out array<int,array<int,Token>> $tokensById
     * @param Token[]|null $scopes
     * @param-out non-empty-array<Token> $scopes
     */
    private function buildHierarchy(
        array &$tokens,
        ?array &$tokensById,
        ?array &$scopes
    ): void {
        $idx = $this->Formatter->TokenIndex;

        $linked = [];
        $tokensById = [];
        $scopes = [reset($tokens)];
        /** @var Token|null */
        $prev = null;
        $index = 0;

        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (
                \PHP_VERSION_ID < 80000
                && $token->id === \T_COMMENT
                && substr($token->text, 0, 2) === '#['
            ) {
                $token->id = \T_ATTRIBUTE_COMMENT;
            }

            $text = $token->text;
            if ($idx->Trimmable[$token->id]) {
                if ($idx->RightTrimmable[$token->id]) {
                    $text = rtrim($text);
                } elseif ($idx->LeftTrimmable[$token->id]) {
                    $text = ltrim($text);
                } else {
                    $text = trim($text);
                }
                if ($text !== $token->text) {
                    $token->setText($text);
                }
            }

            if ($idx->NotCode[$token->id]) {
                if ($token->id === \T_DOC_COMMENT) {
                    $token->Flags |= TokenFlag::DOC_COMMENT;
                } elseif ($token->id === \T_COMMENT) {
                    // "//", "/*" or "#"
                    $token->Flags |= (
                        $text[0] === '/'
                            ? ($text[1] === '/' ? TokenFlag::CPP_COMMENT : TokenFlag::C_COMMENT)
                            : TokenFlag::SHELL_COMMENT
                    );

                    // Make multi-line C-style comments honourary DocBlocks if:
                    // - every line starts with "*", or
                    // - at least one delimiter appears on its own line
                    if (
                        ($token->Flags & TokenFlag::C_COMMENT) === TokenFlag::C_COMMENT
                        && strpos($text, "\n") !== false
                        && (
                            // Every line starts with "*"
                            !Regex::match('/\n\h*+(?!\*)\S/', $text)
                            // The first delimiter is followed by a newline
                            || !Regex::match('/^\/\*++(\h++|(?!\*))\S/', $text)
                            // The last delimiter is preceded by a newline
                            || !Regex::match('/\S((?<!\*)|\h++)\*++\/$/', $text)
                        )
                    ) {
                        $token->Flags |= TokenFlag::INFORMAL_DOC_COMMENT;
                    }
                }
            } else {
                $token->Flags |= TokenFlag::CODE;
            }

            if (
                (
                    $idx->AltContinue[$token->id]
                    || $idx->AltEnd[$token->id]
                ) && $prev && $prev->id !== \T_END_ALT_SYNTAX
            ) {
                if ((
                    $prev->Parent
                    && $prev->Parent->id === \T_COLON
                    && ($idx->AltEnd[$token->id] || (
                        $idx->AltContinueWithExpression[$token->id]
                        && $this->nextSibling($token, 2)->id === \T_COLON
                    ) || (
                        $idx->AltContinueWithNoExpression[$token->id]
                        && $this->nextSibling($token)->id === \T_COLON
                    ))
                ) || (
                    $prev->id === \T_COLON
                    && $prev->isColonAltSyntaxDelimiter()
                )) {
                    $i--;
                    $virtual = new Token(\T_END_ALT_SYNTAX, '');
                    $virtual->Prev = $prev;
                    $virtual->Next = $token;
                    $virtual->Formatter = $this->Formatter;
                    $virtual->Idx = $idx;
                    $virtual->OpenTag = $token->OpenTag;
                    $virtual->CloseTag = &$virtual->OpenTag->CloseTag;
                    $virtual->Flags |= TokenFlag::CODE;
                    $prev->Next = $virtual;
                    $token->Prev = $virtual;
                    $token = $virtual;
                }
            }

            $linked[$index] = $token;
            $tokensById[$token->id][$index] = $token;
            $token->index = $index++;

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
                    /** @var Token */
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
                    $token->Flags |=
                        TokenFlag::CODE
                        | TokenFlag::STATEMENT_TERMINATOR;
                }
            }

            $token->PrevCode = $prev->Flags & TokenFlag::CODE ? $prev : $prev->PrevCode;
            if ($token->Flags & TokenFlag::CODE) {
                $prev->NextCode = $token;
            } else {
                $token->NextCode = &$prev->NextCode;
            }

            $token->Depth = $prev->Depth;
            if (
                $idx->OpenBracket[$prev->id]
                || ($prev->id === \T_COLON && $prev->isColonAltSyntaxDelimiter())
            ) {
                $token->Parent = $prev;
                $token->Depth++;
            } elseif ($idx->CloseBracketOrAlt[$prev->id]) {
                $token->Parent = $prev->Parent;
                $token->Depth--;
            } else {
                $token->Parent = $prev->Parent;
            }

            $token->String = $prev->String;
            $token->Heredoc = $prev->Heredoc;
            if ($idx->StringDelimiter[$prev->id]) {
                if (
                    $prev->String
                    && isset($prev->String->Data[TokenData::STRING_CLOSED_BY])
                    && $prev->String->Data[TokenData::STRING_CLOSED_BY] === $prev
                ) {
                    $token->String = $prev->String->String;
                    if ($prev->id === \T_END_HEREDOC) {
                        assert($prev->Heredoc !== null);
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
                $token->String->Data[TokenData::STRING_CLOSED_BY] = $token;
            }

            if ($idx->CloseBracketOrAlt[$token->id]) {
                assert($token->Parent !== null);
                $opener = $token->Parent;
                $opener->CloseBracket = $token;
                $token->OpenBracket = $opener;
                $token->PrevSibling = &$opener->PrevSibling;
                $token->NextSibling = &$opener->NextSibling;
                $token->Parent = &$opener->Parent;

                // Treat `$token` as a statement terminator if it's a structural
                // `T_CLOSE_BRACE` that doesn't enclose an anonymous function or
                // class
                if (
                    $token->id !== \T_CLOSE_BRACE
                    // Exclude T_CURLY_OPEN and T_DOLLAR_OPEN_CURLY_BRACES
                    || $opener->id !== \T_OPEN_BRACE
                    || !$this->isStructuralBrace($opener)
                ) {
                    $prev = $token;
                    continue;
                }

                $opener->Flags |= TokenFlag::STRUCTURAL_BRACE;
                $token->Flags |= TokenFlag::STRUCTURAL_BRACE;

                $t = $token->PrevSibling;
                while ($t && $idx->DeclarationPartWithNewAndBody[$t->id]) {
                    if (!$idx->ClassOrFunction[$t->id]) {
                        $t = $t->PrevSibling;
                        continue;
                    }
                    if ($t->nextSiblingOf(\T_OPEN_BRACE)->CloseBracket === $token) {
                        /** @var Token */
                        $_next = $t->NextSibling;
                        if ($idx->AfterAnonymousClassOrFunction[$_next->id] || (
                            $idx->Ampersand[$_next->id]
                            // @phpstan-ignore property.nonObject
                            && $idx->AfterAnonymousClassOrFunction[$_next->NextSibling->id]
                        )) {
                            $prev = $token;
                            continue 2;
                        }
                    }
                    break;
                }

                $token->Flags |= TokenFlag::STATEMENT_TERMINATOR;

                $prev = $token;
                continue;
            }

            // If $token continues the previous context (same depth) or is the
            // first token after a close bracket (lower depth), set
            // $token->PrevSibling
            if ($token->Depth <= $prev->Depth && $token->PrevCode) {
                $prevCode = $token->PrevCode->OpenBracket ?? $token->PrevCode;
                if ($prevCode->Parent === $token->Parent) {
                    $token->PrevSibling = $prevCode;
                }
            } elseif ($token->Depth > $prev->Depth) {
                $scopes[] = $token;
            }

            // Then, if there are gaps between siblings, fill them in
            if ($token->Flags & TokenFlag::CODE) {
                if (
                    $token->PrevSibling
                    && !$token->PrevSibling->NextSibling
                ) {
                    $t = $token;
                    assert($t->Prev !== null);
                    do {
                        $t = $t->Prev->OpenBracket ?? $t->Prev;
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

        // @phpstan-ignore paramOut.type
        $tokens = $linked;
    }

    /**
     * Pass 3: resolve statements
     *
     * Token properties set:
     *
     * - `Statement`
     * - `EndStatement`
     *
     * @param Token[] $scopes
     * @param array<int,Token>|null $statements
     * @param-out array<int,Token> $statements
     */
    private function parseStatements(array $scopes, ?array &$statements): void
    {
        $idx = $this->Formatter->TokenIndex;

        $statements = [];

        foreach ($scopes as $scope) {
            if (!($scope->Flags & TokenFlag::CODE)) {
                if (
                    !$scope->NextCode
                    || $scope->NextCode->Parent !== $scope->Parent
                ) {
                    continue;
                }
                $scope = $scope->NextCode;
            }

            $statements[$scope->index] = $statement = $scope;
            $token = $statement;
            while (true) {
                $token->Statement = $statement;
                if ($token !== $statement) {
                    $token->EndStatement = &$statement->EndStatement;
                }
                if ($token->CloseBracket) {
                    $token->CloseBracket->Statement = $statement;
                    $token->CloseBracket->EndStatement = &$statement->EndStatement;
                }

                // The following tokens are regarded as statement terminators:
                //
                // - `T_SEMICOLON`, or `T_CLOSE_BRACE` / `T_CLOSE_TAG` where the
                //   `STATEMENT_TERMINATOR` flag is set, unless the next token
                //   continues an open control structure
                // - `T_COLON` after a switch case or a label
                // - `T_COMMA`:
                //   - between parentheses and square brackets, e.g. in argument
                //     lists, arrays, `for` expressions
                //   - between non-structural braces, e.g. in `match`
                //     expressions

                if (!$token->NextSibling || (
                    (
                        $token->id === \T_SEMICOLON
                        || $token->Flags & TokenFlag::STATEMENT_TERMINATOR
                        || ($token->CloseBracket && $token->CloseBracket->Flags & TokenFlag::STATEMENT_TERMINATOR)
                    ) && (
                        !($next = ($token->CloseBracket ?? $token)->NextCode)
                        || !($idx->ContinuesControlStructure[$next->id] || (
                            $next->id === \T_WHILE
                            && $this->isWhileAfterDo($next)
                        ))
                    )
                ) || (
                    $token->id === \T_COLON
                    && $token->isColonStatementDelimiter()
                ) || (
                    $token->id === \T_COMMA
                    && ($parent = $token->Parent)
                    && ($idx->OpenBracketExceptBrace[$parent->id] || (
                        $parent->id === \T_OPEN_BRACE
                        && !($parent->Flags & TokenFlag::STRUCTURAL_BRACE)
                    ))
                )) {
                    $statement->EndStatement = $token->CloseBracket ?? $token;
                    if (!$token->NextSibling) {
                        break;
                    }
                    $statements[$token->NextSibling->index] = $statement = $token->NextSibling;
                    $token = $statement;
                    continue;
                }

                $token = $token->NextSibling;
            }
        }

        ksort($statements, \SORT_NUMERIC);
    }

    /**
     * Pass 4: resolve expressions
     *
     * Token properties set:
     *
     * - `Expression`
     * - `EndExpression`
     * - `Data[TokenData::NAMED_DECLARATION_PARTS]`
     * - `Data[TokenData::NAMED_DECLARATION_TYPE]`
     * - `Data[TokenData::PROPERTY_HOOKS]`
     * - `Data[TokenData::OTHER_TERNARY_OPERATOR]`
     * - `Data[TokenData::CHAIN_OPENED_BY]`
     *
     * @param Token[] $statements
     * @param array<int,Token>|null $declarations
     * @param-out array<int,Token> $declarations
     * @param array<int,array<int,Token>>|null $declarationsByType
     * @param-out array<int,array<int,Token>> $declarationsByType
     */
    private function parseExpressions(
        array $statements,
        ?array &$declarations,
        ?array &$declarationsByType
    ): void {
        $idx = $this->Formatter->TokenIndex;

        $declarations = [];
        $declarationsByType = [];

        foreach ($statements as $statement) {
            /** @var Token */
            $end = $statement->EndStatement;
            $end = $end->OpenBracket ?? $end;

            if (
                $idx->AttributeOrDeclaration[$statement->id]
                && ($first = $this->skipNextSiblingsFrom($statement, $idx->Attribute))
                && $idx->Declaration[$first->id]
            ) {
                /** @var Token */
                $next = $first->NextCode;
                if (
                    // Limit `static` to:
                    // - `static public ...`
                    // - `static int $foo`
                    // - `static $foo` or `static function` at class level
                    (
                        $first->id !== \T_STATIC
                        || $idx->ModifierOrVar[$next->id]
                        || (
                            $idx->StartOfValueType[$next->id]
                            && ($variable = $this->skipNextSiblingsFrom($next, $idx->ValueType))
                            && $variable->id === \T_VARIABLE
                        )
                        || (
                            ($next->id === \T_VARIABLE || $next->id === \T_FUNCTION)
                            && $this->isClassStatement($statement)
                        )
                    )
                    // Limit `case` to enumerations
                    && ($first->id !== \T_CASE || !$first->inSwitch())
                    && ($parts = $statement->namedDeclarationParts())->count()
                ) {
                    $type = 0;
                    foreach ($parts->getAnyFrom(
                        $idx->DeclarationExceptModifierOrVar
                    )->getIds() as $id) {
                        $type |= self::DECLARATION_MAP[$id];
                    }
                    if (!$type) {
                        if ($parts->hasOneFrom($idx->ModifierOrVar) && $this->isClassStatement($statement)) {
                            $type = Type::PROPERTY;
                        } elseif ($idx->VisibilityOrReadonly[$first->id] && $statement->inParameterList()) {
                            $type = Type::PARAM;
                        }
                    } elseif ($type === Type::_USE && $this->isClassStatement($statement)) {
                        $type = Type::USE_TRAIT;
                    }
                    if ($type) {
                        $statement->Flags |= TokenFlag::NAMED_DECLARATION;
                        $statement->Data[TokenData::NAMED_DECLARATION_PARTS] = $parts;
                        $statement->Data[TokenData::NAMED_DECLARATION_TYPE] = $type;
                        $declarations[$statement->index] = $statement;
                        $declarationsByType[$type][$statement->index] = $statement;

                        if ($type & Type::PROPERTY) {
                            $hooks = [];
                            if (
                                $end->id === \T_OPEN_BRACE
                                && $end->CloseBracket !== $end->NextCode
                            ) {
                                /** @var Token */
                                $current = $end->NextCode;
                                do {
                                    if ($current === $current->Statement) {
                                        $hooks[] = $current;
                                    }
                                } while ($current = $current->NextSibling);
                            }
                            foreach ($hooks as $hook) {
                                $hook->Flags |= TokenFlag::NAMED_DECLARATION;
                                $hook->Data[TokenData::NAMED_DECLARATION_PARTS] = $hook->namedDeclarationParts();
                                $hook->Data[TokenData::NAMED_DECLARATION_TYPE] = Type::HOOK;
                                $declarations[$hook->index] = $hook;
                                $declarationsByType[Type::HOOK][$hook->index] = $hook;
                            }
                            $statement->Data[TokenData::PROPERTY_HOOKS] = new TokenCollection($hooks);
                        }
                    }
                }
            }

            $expression = $statement;
            $token = $expression;
            while (true) {
                // Flag and link ternary operators
                if (
                    $token->id === \T_QUESTION
                    && $token->getSubId() === TokenSubId::QUESTION_TERNARY_OPERATOR
                ) {
                    $current = $token;
                    $count = 0;
                    while (
                        ($current = $current->NextSibling)
                        && $token->EndStatement !== ($current->CloseBracket ?? $current)
                    ) {
                        if (
                            $current->id === \T_QUESTION
                            && $current->getSubId() === TokenSubId::QUESTION_TERNARY_OPERATOR
                        ) {
                            $count++;
                            continue;
                        }
                        if (
                            $current->id === \T_COLON
                            && $current->getSubId() === TokenSubId::COLON_TERNARY_OPERATOR
                        ) {
                            if ($count--) {
                                continue;
                            }
                        } else {
                            continue;
                        }
                        $current->Flags |= TokenFlag::TERNARY_OPERATOR;
                        $token->Flags |= TokenFlag::TERNARY_OPERATOR;
                        $current->Data[TokenData::OTHER_TERNARY_OPERATOR] = $token;
                        $token->Data[TokenData::OTHER_TERNARY_OPERATOR] = $current;
                        break;
                    }
                }

                // Link chained object operators
                if ($idx->Chain[$token->id] && !isset($token->Data[TokenData::CHAIN_OPENED_BY])) {
                    $token->Data[TokenData::CHAIN_OPENED_BY] = $token;
                    $current = $token;
                    while (
                        ($current = $current->NextSibling)
                        && $idx->ChainPart[$current->id]
                    ) {
                        if ($idx->Chain[$current->id]) {
                            $current->Data[TokenData::CHAIN_OPENED_BY] = $token;
                        }
                    }
                }

                // Flag arrow function double arrow operators
                if ($token->id === \T_FN) {
                    $next = $token->nextSiblingOf(\T_DOUBLE_ARROW);
                    $next->Flags |= TokenFlag::FN_DOUBLE_ARROW;
                }

                $isTerminator = false;
                if (
                    !$token->NextSibling
                    || $token === $end
                    || $idx->ExpressionDelimiter[$token->id]
                    || $token->id === \T_SEMICOLON
                    || $token->Flags & TokenFlag::TERNARY_OPERATOR
                ) {
                    // Exclude terminators from expressions
                    if (
                        $idx->ExpressionDelimiter[$token->id]
                        || $idx->StatementDelimiter[$token->id]
                        || $token->Flags & (
                            TokenFlag::STATEMENT_TERMINATOR
                            | TokenFlag::TERNARY_OPERATOR
                        )
                    ) {
                        $isTerminator = true;
                        $last = $token->PrevCode;
                        // Do nothing if the expression is empty
                        if ($last && $last->index >= $expression->index) {
                            $expression->EndExpression = $last;
                        }
                    } else {
                        $expression->EndExpression = $token->CloseBracket ?? $token;
                    }
                }

                if (!$isTerminator) {
                    $token->Expression = $expression;
                    if ($token !== $expression) {
                        $token->EndExpression = &$expression->EndExpression;
                    }
                    if ($token->CloseBracket) {
                        $token->CloseBracket->Expression = $expression;
                        $token->CloseBracket->EndExpression = &$expression->EndExpression;
                    }
                }

                if (!$token->NextSibling || $token === $end) {
                    break;
                }

                $prev = $token;
                $token = $token->NextSibling;
                if ($expression->EndExpression || $isTerminator) {
                    $expression = $token;
                    if ($isTerminator) {
                        $prev->EndExpression = &$expression->EndExpression;
                    }
                }
            }
        }
    }

    private function nextSibling(Token $token, int $offset = 1): Token
    {
        $idx = $this->Formatter->TokenIndex;

        $depth = 0;
        while ($token->Next) {
            if ($idx->OpenBracket[$token->id]) {
                $depth++;
            } elseif ($idx->CloseBracket[$token->id]) {
                $depth--;
                if ($depth < 0) {
                    // @codeCoverageIgnoreStart
                    break;
                    // @codeCoverageIgnoreEnd
                }
            }
            $token = $token->Next;
            while ($idx->NotCode[$token->id]) {
                $token = $token->Next;
                if (!$token) {
                    // @codeCoverageIgnoreStart
                    break 2;
                    // @codeCoverageIgnoreEnd
                }
            }
            if (!$depth) {
                $offset--;
                if (!$offset) {
                    return $token;
                }
            }
        }

        // @codeCoverageIgnoreStart
        throw new ShouldNotHappenException('No sibling found');
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param array<int,bool> $index
     */
    private function skipNextSiblingsFrom(Token $token, array $index): ?Token
    {
        $t = $token;
        while ($t && $index[$t->id]) {
            $t = $t->NextSibling;
        }
        return $t;
    }

    private function isStructuralBrace(Token $token): bool
    {
        if (
            $token->PrevSibling
            && $token->PrevSibling->PrevSibling
            && $token->PrevSibling->PrevSibling->id === \T_MATCH
        ) {
            return false;
        }

        /** @var Token */
        $t = $token->CloseBracket;
        /** @var Token */
        $t = $t->PrevCode;

        // Braces cannot be empty in dereferencing contexts, but they can be in
        // property hooks and trait adaptations
        return $t === $token                                  // `{}`
            || $t->id === \T_SEMICOLON                        // `{ statement; }`
            || $t->id === \T_COLON                            // `{ label: }`
            || ($t->Flags & TokenFlag::STATEMENT_TERMINATOR)  /* `{ statement ?>...<?php }` */
            || (                                              // `{ { statement; } }`
                $t->id === \T_CLOSE_BRACE
                && $t->OpenBracket
                && $t->OpenBracket->id === \T_OPEN_BRACE
                && $this->isStructuralBrace($t->OpenBracket)
            );
    }

    private function isWhileAfterDo(Token $token): bool
    {
        if (
            !$token->PrevSibling
            || !$token->PrevSibling->PrevSibling
        ) {
            // @codeCoverageIgnoreStart
            return false;
            // @codeCoverageIgnoreEnd
        }

        // Check for `do { ... } while ();`
        if ($token->PrevSibling->PrevSibling->id === \T_DO) {
            return true;
        }

        // Check for `do ... while ();` by looking for a previous `T_DO` the
        // token could pair with, starting from its previous sibling because
        // `do` immediately before `while` cannot belong to the same structure
        $do = $token->PrevSibling->prevSiblingOf(\T_DO);
        if ($do->id === \T_NULL) {
            return false;
        }
        // Now look for its `T_WHILE` counterpart, starting from the first token
        // it could be and allowing for nested unenclosed `T_WHILE` loops, i.e.
        // `do while () while (); while ();`
        /** @var Token */
        $next = $do->NextSibling;
        /** @var Token */
        $next = $next->NextSibling;
        foreach ($next->withNextSiblings($token) as $t) {
            if ($t->id === \T_WHILE && !(
                $t->PrevSibling
                && $t->PrevSibling->PrevSibling
                && $t->PrevSibling->PrevSibling->id === \T_WHILE
            )) {
                return $t === $token;
            }
        }

        return false;
    }

    /**
     * Check if a statement is at class level
     *
     * Parent expressions must be resolved before this method is called.
     */
    private function isClassStatement(Token $token): bool
    {
        $idx = $this->Formatter->TokenIndex;

        return $token->Parent
            && $token->Parent->id === \T_OPEN_BRACE
            && $token->Parent
                     ->skipToStartOfDeclaration()
                     ->withNextSiblings($token->Parent)
                     ->hasOneFrom($idx->DeclarationClass);
    }
}
