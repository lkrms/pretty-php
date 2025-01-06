<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\DeclarationType;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\DeclarationRuleTrait;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\DeclarationRule;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;
use Lkrms\PrettyPHP\TokenUtil;
use Salient\Utility\Regex;
use Salient\Utility\Str;

/**
 * Apply standard spacing
 *
 * @api
 */
final class StandardSpacing implements TokenRule, DeclarationRule
{
    use TokenRuleTrait;
    use DeclarationRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 80,
            self::PROCESS_DECLARATIONS => 80,
            self::CALLBACK => 820,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(TokenIndex $idx): array
    {
        return TokenIndex::merge(
            [
                \T_CLOSE_TAG => true,
                \T_COMMA => true,
                \T_DECLARE => true,
                \T_MATCH => true,
                \T_START_HEREDOC => true,
            ],
            $idx->Attribute,
            $idx->OpenTag,
        );
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public static function getDeclarationTypes(array $all): array
    {
        return [
            DeclarationType::PROPERTY => true,
            DeclarationType::PARAM => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedDeclarations(): bool
    {
        return false;
    }

    /**
     * Apply the rule to the given tokens
     *
     * If the indentation level of an open tag aligns with a tab stop, and a
     * close tag is found in the same scope (or the document has no close tag,
     * and the open tag is in the global scope), a callback is registered to
     * align nested tokens with it. An additional level of indentation is
     * applied if `IndentBetweenTags` is enabled.
     *
     * If a `<?php` tag is followed by a `declare` statement, they are collapsed
     * to one line. This is only applied in strict PSR-12 mode if the `declare`
     * statement is `declare(strict_types=1);` (semicolon optional), followed by
     * a close tag.
     *
     * Statements between open and close tags on the same line are preserved as
     * one-line statements, even if they contain constructs that would otherwise
     * break over multiple lines. Similarly, if a pair of open and close tags
     * are both adjacent to code on the same line, newlines between code and
     * tags are suppressed. Otherwise, inner newlines are added to open and
     * close tags.
     *
     * Whitespace is applied to other tokens as follows:
     *
     * - **Commas:** leading whitespace is suppressed, and trailing spaces are
     *   added.
     * - **`declare` expressions:** whitespace is suppressed between
     *   parentheses.
     * - **`match` expressions:** newlines are added after delimiters between
     *   arms.
     * - **Attributes:** in parameters, property hooks, anonymous functions and
     *   arrow functions, spaces are added before and after attributes, and
     *   trailing blank lines are suppressed. For other attributes, leading and
     *   trailing newlines are added.
     * - **Heredocs:** leading newlines are suppressed in strict PSR-12 mode.
     *
     * @prettyphp-callback The `TagIndent` of tokens between indented tags is
     * adjusted by the difference, if any, between the open tag's indent and the
     * indentation level of the first token after the open tag.
     */
    public function processTokens(array $tokens): void
    {
        $idx = $this->Idx;

        foreach ($tokens as $token) {
            if ($token === $token->OpenTag) {
                $closeTag = null;
                $tagIndent = null;
                $innerIndent = null;

                $text = '';
                $current = $token;
                while ($current = $current->Prev) {
                    $text = $current->text . $text;
                    if ($current->id === \T_CLOSE_TAG) {
                        break;
                    }
                }

                // Get the last line of inline HTML before the open tag
                /** @var string */
                $text = strrchr("\n" . $text, "\n");
                $text = substr($text, 1);
                if ($text === '') {
                    $token->TagIndent = 0;
                } elseif (Regex::match('/^\h++$/', $text)) {
                    $indent = strlen(Str::expandTabs($text, $this->Formatter->TabSize));
                    if ($indent % $this->Formatter->TabSize === 0) {
                        $indent = (int) ($indent / $this->Formatter->TabSize);

                        // May be used by `Renderer::renderWhitespaceBefore()`,
                        // even if it isn't used here
                        $token->TagIndent = $indent;

                        // Try to find a close tag in the same scope
                        $current = $token;
                        while ($current->CloseTag) {
                            if ($current->CloseTag->Parent === $token->Parent) {
                                $closeTag = $current->CloseTag;
                                break;
                            }
                            $current = $current->CloseTag;
                            while ($current->Next) {
                                $current = $current->Next;
                                if ($current === $current->OpenTag) {
                                    continue 2;
                                }
                            }
                            break;
                        }

                        if ($closeTag || (!$token->Parent && !$current->CloseTag)) {
                            $tagIndent = $indent;
                            $innerIndent = $tagIndent;
                            if ($this->Formatter->IndentBetweenTags) {
                                $innerIndent++;
                            }
                        }
                    }
                }

                $endOfLine = $token;
                if (
                    $token->id === \T_OPEN_TAG
                    && $this->Formatter->CollapseDeclareHeaders
                    && ($declare = $token->Next)
                    && $declare->id === \T_DECLARE
                    && $declare->NextSibling
                    && ($end = $declare->NextSibling->NextSibling)
                    && $end === $declare->EndStatement
                    && (!$end->NextCode || $end->NextCode->id !== \T_DECLARE)
                ) {
                    $endIsClose = $end->id === \T_CLOSE_TAG
                        || ($end->Next && $end->Next->id === \T_CLOSE_TAG);

                    if (!$this->Formatter->Psr12 || (
                        $endIsClose
                        && !strcasecmp((string) $declare->NextSibling->inner(), 'strict_types=1')
                    )) {
                        $token->Whitespace |= Space::NONE_AFTER;
                        $token->applyWhitespace(Space::SPACE_AFTER);
                        $endOfLine = $end;
                        if ($endIsClose) {
                            /** @var Token */
                            $close = $token->CloseTag;
                            $close->Whitespace |= Space::NONE_BEFORE;
                            $close->applyWhitespace(Space::SPACE_BEFORE);
                        }
                    }
                }
                if ($endOfLine->id !== \T_CLOSE_TAG) {
                    $endOfLine->Whitespace |= Space::LINE_AFTER | Space::SPACE_AFTER;
                }

                // Preserve one-line statements between open and close tags on
                // the same line
                $last = $token->CloseTag ?? $token->last();
                if (
                    $token !== $last
                    && $this->preserveOneLine($token, $last, false, true)
                ) {
                    continue;
                }

                // Suppress newlines between tags and adjacent code on the same
                // line if found at both ends
                if (
                    $token->CloseTag
                    && $token->NextCode
                    && $token->NextCode->index < $token->CloseTag->index
                ) {
                    $nextCode = $token->NextCode;
                    /** @var Token */
                    $lastCode = $token->CloseTag->PrevCode;
                    if (
                        $nextCode->line === $token->line
                        && $lastCode->line === $token->CloseTag->line
                    ) {
                        $this->preserveOneLine($token, $nextCode, true);
                        $this->preserveOneLine($lastCode, $token->CloseTag, true);
                        // If indentation between tags has been added, remove it
                        $innerIndent = $tagIndent;
                    }
                }

                // If indentation applied to `$token->Next` by other rules
                // differs from `$innerIndent`, apply the difference to tokens
                // between `$token` and `$closeTag`, or between `$token` and
                // `$last` if no close tag was found in the same scope
                if ($innerIndent && $token->Next) {
                    $next = $token->Next;
                    $last = $closeTag ?? $last;
                    $this->Formatter->registerCallback(
                        static::class,
                        $next,
                        static function () use ($idx, $innerIndent, $next, $last) {
                            $delta = $innerIndent - $next->getIndent();
                            if ($delta) {
                                foreach ($next->collect($last) as $token) {
                                    if (!$idx->OpenTag[$token->id]) {
                                        $token->TagIndent += $delta;
                                    }
                                }
                            }
                        },
                    );
                }

                continue;
            }

            if ($token->id === \T_CLOSE_TAG) {
                $token->Whitespace |= Space::LINE_BEFORE | Space::SPACE_BEFORE;
                continue;
            }

            if ($token->id === \T_COMMA) {
                $token->Whitespace |= Space::NONE_BEFORE | Space::SPACE_AFTER;
                continue;
            }

            if ($token->id === \T_DECLARE) {
                /** @var Token */
                $nextCode = $token->NextCode;
                $nextCode->outer()->applyInnerWhitespace(Space::NONE);
                continue;
            }

            if ($token->id === \T_MATCH) {
                $parent = $token->nextSiblingOf(\T_OPEN_BRACE);
                /** @var Token */
                $arm = $parent->NextCode;
                if ($arm === $parent->CloseBracket) {
                    continue;
                }
                while (true) {
                    $arm = $arm->nextSiblingOf(\T_DOUBLE_ARROW)
                               ->nextSiblingOf(\T_COMMA);
                    if ($arm->id === \T_NULL) {
                        break;
                    }
                    $arm->Whitespace |= Space::LINE_AFTER;
                }
                continue;
            }

            if ($idx->Attribute[$token->id]) {
                /** @var Token */
                $closedBy = $token->id === \T_ATTRIBUTE
                    ? $token->CloseBracket
                    : $token;
                if (
                    !$token->inParameterList()
                    && !$token->inPropertyHook()
                    && !($token->inAnonymousFunctionOrFn() && !(
                        TokenUtil::isNewlineAllowedAfter($closedBy)
                        && $closedBy->wasLastOnLine()
                    ))
                ) {
                    $token->Whitespace |= Space::LINE_BEFORE;
                    $closedBy->Whitespace |= Space::LINE_AFTER;
                }
                $token->Whitespace |= Space::SPACE_BEFORE;
                $closedBy->Whitespace |= Space::NO_BLANK_AFTER | Space::SPACE_AFTER;
                continue;
            }

            if ($token->id === \T_START_HEREDOC && $this->Formatter->Psr12) {
                $token->Whitespace |= Space::NO_BLANK_BEFORE | Space::NO_LINE_BEFORE | Space::SPACE_BEFORE;
            }
        }
    }

    /**
     * Apply the rule to the given declarations
     *
     * If a constructor has one or more promoted parameters, a newline is added
     * before every parameter.
     *
     * If a property has unimplemented hooks with no modifiers or attributes
     * (e.g. `public $Foo { &get; set; }`), they are collapsed to one line,
     * otherwise hooks with statements are formatted like anonymous functions,
     * and hooks that use abbreviated syntax are formatted like arrow functions.
     */
    public function processDeclarations(array $declarations): void
    {
        $parents = [];
        foreach ($declarations as $token) {
            $type = $token->Data[TokenData::NAMED_DECLARATION_TYPE];

            // Collect promoted constructor parameters
            if ($type === DeclarationType::PARAM) {
                /** @var Token */
                $parent = $token->Parent;
                $parents[$parent->id] = $parent;
            }

            if (
                $type & DeclarationType::PROPERTY
                && ($hooks = $token->Data[TokenData::PROPERTY_HOOKS])->count()
            ) {
                /** @var TokenCollection $hooks */
                $collapse = true;
                foreach ($hooks as $hook) {
                    $hasAttribute = $this->Idx->Attribute[$hook->id];
                    $name = $hook->skipNextSiblingFrom($this->Idx->AttributeOrModifier);
                    $hasModifier = $name !== $hook
                        && $name->PrevSibling
                        && !$this->Idx->Attribute[$name->PrevSibling->id];
                    $name = $name->skipNextSiblingFrom($this->Idx->Ampersand);
                    /** @var Token */
                    $next = $name->NextSibling;
                    if ($hasParameters = $next->id === \T_OPEN_PARENTHESIS) {
                        /** @var Token */
                        $next = $next->NextSibling;
                    }
                    // Format `set () {}` like `function () {}`
                    if ($hasBody = $next->id === \T_OPEN_BRACE) {
                        $name->Whitespace |= Space::SPACE_AFTER;
                    }
                    $hasExpression = $next->id === \T_DOUBLE_ARROW;
                    $collapse = $collapse && !(
                        $hasAttribute
                        || $hasModifier
                        || $hasParameters
                        || $hasBody
                        || $hasExpression
                    );
                }

                // Collapse hooks like `{ &get; set; }` to one line
                if ($collapse) {
                    /** @var Token */
                    $end = $token->EndStatement;
                    /** @var Token */
                    $start = $end->OpenBracket;
                    $this->preserveOneLine($start, $end, true);
                }
            }
        }

        foreach ($parents as $parent) {
            /** @var TokenCollection */
            $items = $parent->Data[TokenData::LIST_ITEMS];
            foreach ($items as $item) {
                $item->Whitespace |= Space::LINE_BEFORE;
            }
        }
    }
}
