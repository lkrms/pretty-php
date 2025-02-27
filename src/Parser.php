<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\TokenSubId as SubId;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Exception\InvalidSyntaxException;
use Lkrms\PrettyPHP\Internal\Document;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Get;
use Salient\Utility\Regex;

/**
 * @api
 */
final class Parser implements Immutable
{
    use ImmutableTrait;

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
        $idx = $this->Formatter->TokenIndex;

        /** @var Token|null */
        $prev = null;

        foreach ($tokens as $token) {
            $token->Formatter = $this->Formatter;
            $token->Idx = $idx;

            if ($prev) {
                $token->Prev = $prev;
                $prev->Next = $token;

                // Make code like this fail with an exception on PHP 7.4:
                /* class Foo { use Bar { x as y?><?= as z; } } */
                // See https://github.com/php/php-src/commit/55717656097918baf21fe272a788db501ed33854
                if (
                    \PHP_VERSION_ID < 80000
                    && $prev->CloseTag === $prev
                    && $token->id === \T_STRING
                    && $token->text === '<?='
                ) {
                    throw new InvalidSyntaxException(sprintf(
                        '%s error in %s:%d: Cannot use "<?=" as an identifier',
                        Get::basename(static::class),
                        $this->Formatter->Filename ?? '<input>',
                        $token->line,
                    ));
                }
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
            if ($idx->OpenTag[$token->id]) {
                $token->OpenTag = $token;
            } elseif ($prev && $prev->OpenTag && !$prev->CloseTag) {
                $token->OpenTag = $prev->OpenTag;
                $token->CloseTag = &$token->OpenTag->CloseTag;

                if ($token->id === \T_CLOSE_TAG) {
                    $token->OpenTag->CloseTag = $token;
                }
            }

            $prev = $token;
        }
    }

    /**
     * Pass 2: build token hierarchy
     *
     * - add virtual brackets around unenclosed control structure bodies
     * - add virtual close brackets after alternative syntax blocks
     * - on PHP < 8.0, convert comments that appear to be PHP >= 8.0 attributes
     *   to `T_ATTRIBUTE_COMMENT`
     * - trim token text
     * - apply comment type flags
     * - detect comments that should receive the same treatment as DocBlocks
     * - detect close tags that are statement terminators
     * - flag code tokens
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
     * - `Data[Data::END_STRING]`
     *
     * @param non-empty-list<Token> $tokens
     * @param array<int,array<int,Token>>|null $tokensById
     * @param Token[]|null $scopes
     * @param-out non-empty-list<Token> $tokens
     * @param-out array<int,array<int,Token>> $tokensById
     * @param-out non-empty-array<Token> $scopes
     */
    private function buildHierarchy(
        array &$tokens,
        ?array &$tokensById,
        ?array &$scopes
    ): void {
        $idx = $this->Formatter->TokenIndex;

        $built = [];
        $tokensById = [];
        $scopes = [reset($tokens)];
        $unenclosed = [];
        /** @var Token|null */
        $lastUnenclosed = null;
        /** @var Token|null */
        $prev = null;
        $index = 0;

        // Add a token with ID 0 to ensure `T_OPEN_UNENCLOSED` tokens always
        // have `T_CLOSE_UNENCLOSED` counterparts
        $last = end($tokens);
        $token = new Token(0, '', $last->getEndLine(), $last->getEndPos());
        if ($last->column > 0) {
            $token->column = $last->getEndColumn();
        }
        $tokens[] = $token;
        $i = 0;

        // This closure is used in the loop below to replace the current token
        // with a virtual token
        $addVirtual = static function (int $id, bool $bindNext = true) use (
            &$token,
            &$prev,
            &$i,
            $idx
        ) {
            /** @var Token $prev */
            $i--;
            $virtual = $bindNext
                ? new Token($id, '', $token->line, $token->pos)
                : new Token($id, '', $prev->getEndLine(), $prev->getEndPos());
            $virtual->column = $bindNext
                ? $token->column
                : $prev->getEndColumn();
            $virtual->Formatter = $prev->Formatter;
            $virtual->Idx = $prev->Idx;
            $virtual->Prev = $prev;
            $prev->Next = $virtual;
            if ($token->id !== 0) {
                $virtual->Next = $token;
                $token->Prev = $virtual;
            }
            $virtual->OpenTag = $prev->OpenTag;
            $virtual->CloseTag = &$virtual->OpenTag->CloseTag;

            $realPrev = $prev->skipPrevFrom($idx->Virtual);
            $virtual->Data[Data::PREV_REAL] = $realPrev;
            $virtual->Data[Data::NEXT_REAL] = $token->id !== 0 ? $token : null;
            if ($bindNext) {
                $virtual->Data[Data::BOUND_TO] = $token;
                $virtual->Whitespace = &$token->Whitespace;
            } else {
                $virtual->Data[Data::BOUND_TO] = $realPrev;
                $virtual->Whitespace = &$realPrev->Whitespace;
            }
            $token = $virtual;
        };

        for (;; $i++) {
            $token = $tokens[$i];

            $prevCode = $prev
                ? ($prev->Flags & Flag::CODE
                    ? $prev
                    : $prev->PrevCode)
                : null;

            // Add virtual `T_OPEN_UNENCLOSED` and `T_CLOSE_UNENCLOSED` brackets
            // around unenclosed control structure bodies
            $hasOptional = false;
            $hasOptionalWithExpression = false;
            if (
                ($hasBody = $prevCode)
                && !$idx->OutsideCode[$token->id]
                && (
                    ($hasOptional = $idx->HasOptionalBracesWithNoExpression[$hasBody->id])
                    || ($hasOptionalWithExpression = (
                        $prevCode->id === \T_CLOSE_PARENTHESIS
                        && ($hasBody = $prevCode->PrevSibling)
                        && $idx->HasOptionalBracesWithExpression[$hasBody->id]
                    ))
                    || $lastUnenclosed
                )
            ) {
                // The first token enclosed may not be code, so find the next
                // code token or close tag for unenclosed body checks
                $code = $token;
                while ($code->id !== \T_CLOSE_TAG && $idx->NotCode[$code->id]) {
                    $code = $code->Next;
                    if (!$code) {
                        $code = $token;
                        break;
                    }
                }

                if (
                    $code->id !== \T_OPEN_BRACE
                    && ($code->id === \T_CLOSE_TAG || !$idx->NotCode[$code->id])
                    && (
                        (
                            $hasOptional && !(
                                $hasBody->id === \T_ELSE && (
                                    // Ignore alternative syntax constructs
                                    $code->id === \T_COLON
                                    // Treat `else if` as `elseif`
                                    || $code->id === \T_IF
                                )
                            )
                        ) || (
                            $hasOptionalWithExpression && !(
                                // Ignore alternative syntax constructs
                                $code->id === \T_COLON
                                && $idx->AltStartOrContinue[$hasBody->id]
                            ) && !(
                                // Ignore `while` in `do ... while`
                                $hasBody->id === \T_WHILE
                                && ($prevSibling = $hasBody->PrevSibling)
                                && $prevSibling->PrevCode
                                && $prevSibling->PrevCode->id === \T_DO
                            ) && !(
                                // Ignore `declare(...);`
                                $hasBody->id === \T_DECLARE && (
                                    $code->id === \T_SEMICOLON
                                    || $code->id === \T_CLOSE_TAG
                                )
                            )
                        )
                    )
                ) {
                    $addVirtual(\T_OPEN_UNENCLOSED, false);

                    $hasBody->Flags |= Flag::UNENCLOSED_PARENT;
                    $token->Data[Data::UNENCLOSED_PARENT] = $hasBody;

                    if ($lastUnenclosed) {
                        $unenclosed[] = $lastUnenclosed;
                    }
                    $lastUnenclosed = $token;
                } elseif (
                    $lastUnenclosed && (
                        $prevCode->Parent === $lastUnenclosed
                        /*
                         * In structures like `if (...) ?>`, allow unenclosed
                         * blocks to be closed with no inner tokens if they
                         * don't continue like `elseif (...) ?><?php else ...`
                         */
                        || (
                            $token->id === \T_CLOSE_TAG
                            && $prevCode === $lastUnenclosed
                        )
                    )
                ) {
                    // Close the block if `$prevCode` is a statement terminator
                    // or `$token` is a close tag (`T_CLOSE_TAG` is handled
                    // early so virtual tokens aren't added outside PHP tags)
                    $continues = null;
                    if ($prevCode->id === \T_SEMICOLON || (
                        $prevCode->Flags & Flag::TERMINATOR && !(
                            $prevCode->id === \T_CLOSE_BRACE
                            && $this->continuesEnclosed($code, $prevCode)
                        )
                    ) || (
                        $prevCode->id === \T_COLON
                        && $prevCode->isColonStatementDelimiter()
                    ) || (
                        $prevCode->id === \T_CLOSE_UNENCLOSED
                        && !$this->continuesUnenclosed($code, $prevCode)
                        && !(
                            $token->id === \T_CLOSE_TAG
                            && ($continues = $this->continuesUnenclosed($code, $lastUnenclosed))
                        )
                    ) || (
                        $token->id === \T_CLOSE_TAG
                        && $prevCode->id !== \T_CLOSE_UNENCLOSED
                        && !($continues ??= $this->continuesUnenclosed($code, $lastUnenclosed))
                    )) {
                        $continues ??= $this->continuesUnenclosed($code, $lastUnenclosed);
                        $lastUnenclosed->Data[Data::UNENCLOSED_CONTINUES] = $continues;

                        $addVirtual(\T_CLOSE_UNENCLOSED, $continues);

                        $lastUnenclosed = array_pop($unenclosed);
                    }
                }
            }

            if ($token->id === 0) {
                break;
            }

            // Add virtual `T_CLOSE_ALT` brackets after alternative syntax
            // blocks to close their `T_COLON` open brackets
            if ($idx->AltContinueOrEnd[$token->id]) {
                /** @var Token $prev */
                if ($prev->id !== \T_CLOSE_ALT && (
                    (
                        $prev->Parent
                        && $prev->Parent->id === \T_COLON
                        && ($idx->AltEnd[$token->id] || (
                            $token->id === \T_ELSEIF
                            && $this->nextSibling($token, 2)->id === \T_COLON
                        ) || (
                            $token->id === \T_ELSE
                            && $this->nextSibling($token)->id === \T_COLON
                        ))
                    ) || (
                        $prev->id === \T_COLON
                        && $prev->isColonAltSyntaxDelimiter()
                    )
                )) {
                    $addVirtual(\T_CLOSE_ALT);
                }
            }

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

            $built[$index] = $token;
            $tokensById[$token->id][$index] = $token;
            $token->index = $index++;

            if (!$prev) {
                $prev = $token;
                continue;
            }

            if ($token->id === \T_DOC_COMMENT) {
                $token->Flags |= Flag::DOC_COMMENT;
            } elseif ($token->id === \T_COMMENT) {
                // "//", "/*" or "#"
                $token->Flags |= (
                    $text[0] === '/'
                        ? ($text[1] === '/' ? Flag::CPP_COMMENT : Flag::C_COMMENT)
                        : Flag::SHELL_COMMENT
                );

                // Treat multi-line C-style comments as DocBlocks if:
                // - every line starts with "*", or
                // - at least one delimiter appears on its own line
                if (
                    ($token->Flags & Flag::C_COMMENT) === Flag::C_COMMENT
                    && strpos($text, "\n") !== false
                    && (
                        // Every line starts with "*"
                        !Regex::match('/\n\h*+(?!\*)\S/', $text)
                        // The first delimiter is followed by a newline
                        || !Regex::match('/^\/\*++(\h++|(?!\*))\S/', $text)
                        // The last delimiter is preceded by a newline
                        || !Regex::match('/\S((?<!\*)|\h++)\*++\/$/D', $text)
                    )
                ) {
                    $token->Flags |= Flag::C_DOC_COMMENT;
                }
            }

            // Check if close tags are statement terminators
            if ($token->id === \T_CLOSE_TAG) {
                if (
                    ($t = $prev->skipPrevFrom($idx->NotCodeBeforeCloseTag)) !== $token->OpenTag
                    && $t->id !== \T_SEMICOLON
                    && $t->id !== \T_COLON
                    && $t->id !== \T_OPEN_BRACE
                    && (
                        $t->id !== \T_CLOSE_BRACE
                        || !($t->Flags & Flag::TERMINATOR)
                    )
                ) {
                    $token->Flags |= Flag::CODE | Flag::TERMINATOR;
                }
            } elseif (!$idx->NotCode[$token->id]) {
                $token->Flags |= Flag::CODE;
            }

            $token->PrevCode = $prevCode;
            if ($token->Flags & Flag::CODE) {
                $prev->NextCode = $token;
            } else {
                $token->NextCode = &$prev->NextCode;
            }

            // Adopt parent and depth from `$prev` unless it's a bracket
            $token->Depth = $prev->Depth;
            if (
                $idx->OpenBracket[$prev->id]
                || $prev->id === \T_OPEN_UNENCLOSED
                || ($prev->id === \T_COLON && $prev->isColonAltSyntaxDelimiter())
            ) {
                $token->Parent = $prev;
                $token->Depth++;
            } elseif ($idx->CloseBracketOrVirtual[$prev->id]) {
                $token->Parent = $prev->Parent;
                $token->Depth--;
            } else {
                $token->Parent = $prev->Parent;
            }

            // Handle nested strings in a similar way
            $token->String = $prev->String;
            $token->Heredoc = $prev->Heredoc;
            if ($idx->StringDelimiter[$prev->id]) {
                if (
                    $prev->String
                    && isset($prev->String->Data[Data::END_STRING])
                    && $prev->String->Data[Data::END_STRING] === $prev
                ) {
                    $token->String = $prev->String->String;
                    if ($prev->id === \T_END_HEREDOC) {
                        /** @var Token */
                        $heredoc = $prev->Heredoc;
                        $token->Heredoc = $heredoc->Heredoc;
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
                $token->String->Data[Data::END_STRING] = $token;
            }

            if ($idx->CloseBracketOrVirtual[$token->id]) {
                /** @var Token */
                $opener = $token->Parent;
                $opener->CloseBracket = $token;
                $token->OpenBracket = $opener;
                $token->PrevSibling = &$opener->PrevSibling;
                $token->NextSibling = &$opener->NextSibling;
                $token->Parent = &$opener->Parent;

                if (
                    $opener->id === \T_OPEN_BRACE
                    && $this->isStructuralBrace($opener)
                ) {
                    $opener->Flags |= Flag::STRUCTURAL_BRACE;
                    $token->Flags |= Flag::STRUCTURAL_BRACE;

                    // Flag structural close braces as statement terminators
                    // unless they close an anonymous class or function body
                    $isTerminator = true;
                    $t = $token;
                    while (
                        ($t = $t->PrevSibling)
                        && $idx->DeclarationPartWithNewAndBody[$t->id]
                        && $t->id !== \T_OPEN_BRACE
                    ) {
                        if ($idx->ClassOrFunction[$t->id]) {
                            /** @var Token */
                            $t = $t->NextSibling;
                            if ($idx->AfterAnonymousClassOrFunction[$t->id] || (
                                $idx->Ampersand[$t->id]
                                && ($t = $t->NextSibling)
                                && $idx->AfterAnonymousClassOrFunction[$t->id]
                            )) {
                                $isTerminator = false;
                            }
                            break;
                        }
                    }
                    if ($isTerminator) {
                        $token->Flags |= Flag::TERMINATOR;
                    }
                }

                $prev = $token;
                continue;
            }

            // If `$token` continues the previous context (same depth) or is the
            // first token after a close bracket (lower depth), set
            // `$token->PrevSibling`
            if ($token->Depth <= $prev->Depth && $token->PrevCode) {
                $prevSibling = $token->PrevCode->OpenBracket ?? $token->PrevCode;
                if ($prevSibling->Parent === $token->Parent) {
                    $token->PrevSibling = $prevSibling;
                }
            } elseif ($token->Depth > $prev->Depth) {
                $scopes[] = $token;
            }

            // Then, if there are gaps between siblings, fill them in
            if ($token->Flags & Flag::CODE) {
                if (!$token->PrevSibling) {
                    $t = $token;
                    while (($t = $t->Prev) && $t->Parent === $token->Parent) {
                        $t->NextSibling = $token;
                    }
                } elseif (!$token->PrevSibling->NextSibling) {
                    /** @var Token */
                    $t = $token->Prev;
                    do {
                        $t = $t->OpenBracket ?? $t;
                        $t->NextSibling = $token;
                    } while ($t !== $token->PrevSibling && ($t = $t->Prev));
                }
            }

            $prev = $token;
        }

        // @phpstan-ignore paramOut.type
        $tokens = $built;
    }

    /**
     * Pass 3: parse statements
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
            if (!($scope->Flags & Flag::CODE)) {
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

                // Treat the following as statement terminators:
                //
                // - `T_SEMICOLON`
                // - `T_CLOSE_BRACE` / `T_CLOSE_TAG` where the `TERMINATOR` flag
                //   is set, or `T_CLOSE_UNENCLOSED`, unless the next token
                //   continues a control structure
                // - `T_COLON` after a switch case or a label
                // - `T_COMMA`:
                //   - between parentheses and square brackets, e.g. in argument
                //     lists, arrays, `for` expressions
                //   - between non-structural braces, e.g. in `match`
                //     expressions
                //   - not after `implements` in anonymous class declarations
                if (
                    !$token->NextSibling
                    || $token->id === \T_SEMICOLON
                    || $token->Flags & Flag::TERMINATOR
                    || (
                        ($close = $token->CloseBracket)
                        && (
                            $close->id === \T_CLOSE_UNENCLOSED
                            || $close->Flags & Flag::TERMINATOR
                        ) && (
                            !($next = $close->NextCode) || !(
                                $idx->ContinuesControlStructure[$next->id] || (
                                    $next->id === \T_WHILE
                                    && ($prev = $next->PrevSibling)
                                    && ($prev = $prev->PrevCode)
                                    && $prev->id === \T_DO
                                )
                            )
                        )
                    ) || (
                        $token->id === \T_COLON
                        && $token->isColonStatementDelimiter()
                    ) || (
                        $token->id === \T_COMMA
                        && ($parent = $token->Parent)
                        && ($idx->OpenBracketExceptBrace[$parent->id] || (
                            $parent->id === \T_OPEN_BRACE
                            && !($parent->Flags & Flag::STRUCTURAL_BRACE)
                        ))
                        // Necessary to prevent interfaces implemented by
                        // anonymous classes becoming statements
                        && $token->skipPrevCodeFrom($idx->DeclarationList)->id !== \T_IMPLEMENTS
                    )
                ) {
                    $end = $token->CloseBracket ?? $token;
                    if (
                        $idx->Virtual[$end->id]
                        && $end->NextCode
                        && $end->NextCode->id === \T_CLOSE_TAG
                    ) {
                        $end = $end->NextCode;
                        $end->Statement = $statement;
                        $end->EndStatement = &$statement->EndStatement;
                    }
                    $statement->EndStatement = $end;
                    if (!$end->NextSibling) {
                        break;
                    }
                    $statements[$end->NextSibling->index] = $statement = $end->NextSibling;
                    $token = $statement;
                    continue;
                }

                $token = $token->NextSibling;
            }
        }

        ksort($statements, \SORT_NUMERIC);
    }

    /**
     * Pass 4: parse expressions
     *
     * Token properties set:
     *
     * - `Data[Data::DECLARATION_PARTS]`
     * - `Data[Data::DECLARATION_TYPE]`
     * - `Data[Data::PROPERTY_HOOKS]`
     * - `Data[Data::FOR_PARTS]`
     * - `Data[Data::OTHER_TERNARY]`
     * - `Data[Data::CHAIN]`
     *
     * @param Token[] $statements
     * @param array<int,Token>|null $declarations
     * @param array<int,array<int,Token>>|null $declarationsByType
     * @param-out array<int,Token> $declarations
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

            // Parse non-anonymous declarations and `for` expressions
            if (
                $idx->AttributeOrDeclaration[$statement->id]
                && ($first = $this->skipNextSiblingsFrom($statement, $idx->Attribute))
                && $idx->Declaration[$first->id]
                && !(
                    $idx->ConstOrFunction[$first->id]
                    && $first->Parent
                    && $first->Parent->id === \T_OPEN_BRACE
                    && !($first->Parent->Flags & Flag::STRUCTURAL_BRACE)
                )
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
                        ) || (
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
                        if (
                            $parts->hasOneFrom($idx->ModifierOrVar)
                            && $this->isClassStatement($statement)
                        ) {
                            $type = Type::PROPERTY;
                        } elseif (
                            $idx->VisibilityOrReadonly[$first->id]
                            && $statement->inParameterList()
                        ) {
                            $type = Type::PARAM;
                        }
                    } elseif (
                        $type === Type::_USE
                        && $this->isClassStatement($statement)
                    ) {
                        $type = Type::USE_TRAIT;
                    }
                    if ($type) {
                        $statement->Flags |= Flag::DECLARATION;
                        $statement->Data[Data::DECLARATION_PARTS] = $parts;
                        $statement->Data[Data::DECLARATION_TYPE] = $type;
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
                                $hook->Flags |= Flag::DECLARATION;
                                $hook->Data[Data::DECLARATION_PARTS] = $hook->namedDeclarationParts();
                                $hook->Data[Data::DECLARATION_TYPE] = Type::HOOK;
                                $declarations[$hook->index] = $hook;
                                $declarationsByType[Type::HOOK][$hook->index] = $hook;
                            }
                            $statement->Data[Data::PROPERTY_HOOKS] = new TokenCollection($hooks);
                        }
                    }
                }
            } elseif ($statement->id === \T_FOR) {
                /** @var Token */
                $open = $statement->NextCode;
                /** @var Token */
                $close = $open->CloseBracket;
                /** @var Token */
                $first = $open->Next;
                /** @var Token */
                $last = $close->Prev;

                $children = $open->children();
                $semicolons = $children->getAnyOf(\T_SEMICOLON);
                $commas = $children->getAnyOf(\T_COMMA);
                /** @var Token */
                $semi1 = $semicolons->first();
                /** @var Token */
                $second = $semi1->Next;
                /** @var Token */
                $semi2 = $semicolons->last();
                /** @var Token */
                $third = $semi2->Next;

                $expr1 = $first->collect($semi1);
                $expr2 = $second->collect($semi2);
                $expr3 = $third->collect($last);

                $statement->Data[Data::FOR_PARTS] = [
                    $expr1,
                    $expr2,
                    $expr3,
                    $semicolons,
                    $commas,
                ];
            }

            $token = $statement;
            while (true) {
                // Flag and link ternary operators
                if (
                    $token->id === \T_QUESTION
                    && $token->getSubId() === SubId::QUESTION_TERNARY
                ) {
                    $current = $token;
                    $count = 0;
                    while (
                        ($current = $current->NextSibling)
                        && $token->EndStatement !== ($current->CloseBracket ?? $current)
                    ) {
                        if (
                            $current->id === \T_QUESTION
                            && $current->getSubId() === SubId::QUESTION_TERNARY
                        ) {
                            $count++;
                            continue;
                        }
                        if (
                            $current->id === \T_COLON
                            && $current->getSubId() === SubId::COLON_TERNARY
                        ) {
                            if ($count--) {
                                continue;
                            }
                        } else {
                            continue;
                        }
                        $current->Flags |= Flag::TERNARY;
                        $token->Flags |= Flag::TERNARY;
                        $current->Data[Data::OTHER_TERNARY] = $token;
                        $token->Data[Data::OTHER_TERNARY] = $current;
                        break;
                    }
                }

                // Link chained object operators
                if (
                    $idx->Chain[$token->id]
                    && !isset($token->Data[Data::CHAIN])
                ) {
                    $token->Data[Data::CHAIN] = $token;
                    $current = $token;
                    while (
                        ($current = $current->NextSibling)
                        && $idx->ChainPart[$current->id]
                    ) {
                        if ($idx->Chain[$current->id]) {
                            $current->Data[Data::CHAIN] = $token;
                        }
                    }
                }

                // Flag arrow function double arrow operators
                if ($token->id === \T_FN) {
                    $next = $token->nextSiblingOf(\T_DOUBLE_ARROW);
                    $next->Flags |= Flag::FN_DOUBLE_ARROW;
                }

                if (!$token->NextSibling || $token === $end) {
                    break;
                }

                $token = $token->NextSibling;
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
                    break;
                }
            }
            $token = $token->Next;
            while ($idx->NotCode[$token->id]) {
                $token = $token->Next;
                if (!$token) {
                    break 2;
                }
            }
            if (!$depth) {
                $offset--;
                if (!$offset) {
                    return $token;
                }
            }
        }

        return new Token(\T_NULL, '');
    }

    /**
     * @param array<int,bool> $index
     */
    private function skipNextSiblingsFrom(Token $token, array $index): ?Token
    {
        while ($index[$token->id]) {
            if (!$token->NextSibling) {
                return null;
            }
            $token = $token->NextSibling;
        }
        return $token;
    }

    private function isStructuralBrace(Token $token): bool
    {
        if (
            ($prev = $token->PrevSibling)
            && ($prev = $prev->PrevSibling)
            && $prev->id === \T_MATCH
        ) {
            return false;
        }

        /** @var Token */
        $t = $token->CloseBracket;
        /** @var Token */
        $t = $t->PrevCode;

        // Braces cannot be empty in dereferencing contexts, but they can be in
        // property hooks and trait adaptations
        return $t === $token                   // `{}`
            || $t->id === \T_SEMICOLON         // `{ statement; }`
            || $t->id === \T_COLON             // `{ label: }`
            || ($t->Flags & Flag::TERMINATOR)  /* `{ statement ?>...<?php }` */
            || $t->id === \T_CLOSE_UNENCLOSED  // `{ if (...) statement; }`
            || (                               // `{ { statement; } }`
                $t->id === \T_CLOSE_BRACE
                && $t->OpenBracket
                && $t->OpenBracket->id === \T_OPEN_BRACE
                && $this->isStructuralBrace($t->OpenBracket)
            );
    }

    /**
     * @param Token $parent Either a control structure with an unenclosed body,
     * or a `T_CLOSE_UNENCLOSED`.
     */
    private function continuesUnenclosed(Token $token, Token $parent): bool
    {
        $idx = $this->Formatter->TokenIndex;
        if ($token->id === \T_CLOSE_TAG) {
            $token = $this->nextSibling($token);
        }

        if ($parent->OpenBracket) {
            $open = $parent->OpenBracket;
            $parent = $open->Data[Data::UNENCLOSED_PARENT];
        } elseif ($parent->id === \T_OPEN_UNENCLOSED) {
            $parent = $parent->Data[Data::UNENCLOSED_PARENT];
        }

        return ($token->id === \T_WHILE && $parent->id === \T_DO)
            || ($idx->ElseIfOrElse[$token->id] && $idx->IfOrElseIf[$parent->id]);
    }

    private function continuesEnclosed(Token $token, Token $close): bool
    {
        $idx = $this->Formatter->TokenIndex;
        if ($token->id === \T_CLOSE_TAG) {
            $token = $this->nextSibling($token);
        }

        /** @var Token */
        $open = $close->OpenBracket;

        return ($parent = $open->PrevSibling) && (
            ($token->id === \T_WHILE && $parent->id === \T_DO)
            || ($idx->CatchOrFinally[$token->id] && $parent->id === \T_TRY)
            || (
                ($parent = $parent->PrevSibling) && (
                    ($idx->ElseIfOrElse[$token->id] && $idx->IfOrElseIf[$parent->id])
                    || ($idx->CatchOrFinally[$token->id] && $parent->id === \T_CATCH)
                )
            )
        );
    }

    /**
     * Check if a statement is at class level
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
