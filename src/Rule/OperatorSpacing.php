<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Token;

/**
 * Apply whitespace to operators
 *
 * @api
 */
final class OperatorSpacing implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 102,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return $idx->Operator;
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * Apply the rule to the given tokens
     *
     * Operators in `declare` expressions are ignored.
     *
     * Whitespace is suppressed:
     *
     * - after reference-related ampersands
     * - before and after operators in union, intersection and DNF types
     * - between parentheses in DNF types
     * - before and after exception delimiters in `catch` blocks (unless strict
     *   PSR-12 mode is enabled)
     * - after `?` in nullable types
     * - between `++` and `--` and the variables they operate on
     * - after other unary operators
     * - before `:` in short ternary expressions, e.g. `$a ?: $b`
     *
     * A space is added:
     *
     * - before reference-related ampersands
     * - before DNF types that start with an open parenthesis
     * - before `?` in nullable types
     * - before and after `:` in standard ternary expressions
     * - after `:` in other contexts
     *
     * Spaces are added before and after operators not mentioned above.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $parent = $token->Parent;

            if (
                $parent
                && $parent->PrevCode
                && $parent->PrevCode->id === \T_DECLARE
            ) {
                continue;
            }

            /** @var Token */
            $next = $token->Next;
            $prev = $token->Prev;
            $prevCode = $token->PrevCode;

            if (
                $this->Idx->Ampersand[$token->id]
                && $next->Flags & Flag::CODE
                && (
                    // `function &getValue()`
                    ($prevCode && $this->Idx->FunctionOrFn[$prevCode->id])
                    // `public $Foo { &get; }`
                    || ($parent && $token->inPropertyHook())
                    // `[&$variable]`, `$a = &getValue()`
                    || $token->inUnaryContext()
                    // `function foo(&$bar)`, `function foo($bar, &...$baz)`
                    || (
                        ($next->id === \T_VARIABLE || $next->id === \T_ELLIPSIS)
                        && $token->inParameterList()
                        // Not `function getValue($param = $a & $b)`
                        && !$token->sinceStatement()->hasOneOf(\T_VARIABLE)
                    )
                )
            ) {
                $token->Whitespace |= Space::SPACE_BEFORE | Space::NONE_AFTER;
            } elseif (
                $this->Idx->TypeDelimiter[$token->id] && (
                    ($inTypeContext = $this->inTypeContext($token)) || (
                        $token->id === \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
                        && $parent
                        && $parent->id === \T_OPEN_PARENTHESIS
                        && (
                            (
                                $parent->PrevCode
                                && $parent->PrevCode->id === \T_OR
                            ) || (
                                $parent->CloseBracket
                                && $parent->CloseBracket->NextCode
                                && $parent->CloseBracket->NextCode->id === \T_OR
                            )
                        )
                        && $this->inTypeContext($parent)
                    )
                )
            ) {
                $token->Whitespace |= Space::NONE_BEFORE | Space::NONE_AFTER;
                if (!$inTypeContext) {
                    /** @var Token $parent */
                    $parent->outer()->setInnerWhitespace(Space::NONE);
                    if (
                        $parent->PrevCode
                        && $parent->PrevCode->id !== \T_OR
                    ) {
                        $parent->Whitespace |= Space::SPACE_BEFORE;
                    }
                }
            } elseif (
                $token->id === \T_OR
                && $parent
                && $parent->PrevCode
                && $parent->PrevCode->id === \T_CATCH
                && !$this->Formatter->Psr12
            ) {
                $token->Whitespace |= Space::NONE_BEFORE | Space::NONE_AFTER;
            } elseif (
                $token->id === \T_QUESTION
                && !($token->Flags & Flag::TERNARY)
            ) {
                $token->Whitespace |= Space::SPACE_BEFORE | Space::NONE_AFTER;
            } elseif (
                $token->id === \T_INC
                || $token->id === \T_DEC
            ) {
                $token->Whitespace |= $prev
                    && $this->Idx->EndOfVariable[$prev->id]
                    && !($prev->Flags & Flag::STRUCTURAL_BRACE)
                        ? Space::NONE_BEFORE
                        : Space::NONE_AFTER;
            } elseif (
                $token->isUnaryOperator()
                && $next->Flags & Flag::CODE
                && (
                    !$this->Idx->Operator[$next->id]
                    || $next->isUnaryOperator()
                )
            ) {
                $token->Whitespace |= Space::NONE_AFTER;
            } elseif ($token->id === \T_COLON) {
                $token->Whitespace |= (
                    $token->Flags & Flag::TERNARY
                        ? ($token->Data[Data::OTHER_TERNARY] === $prev
                            ? Space::NONE_BEFORE
                            : Space::SPACE_BEFORE)
                        : 0
                ) | Space::SPACE_AFTER;
            } else {
                $token->Whitespace |= Space::SPACE_AFTER | Space::SPACE_BEFORE;
            }
        }
    }

    /**
     * Check if a T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG or T_OR token, or a
     * T_OPEN_PARENTHESIS token enclosing one of them, belongs to a native type
     */
    private function inTypeContext(Token $token): bool
    {
        return (
            $token->inDeclaration()
            && !$token->inPropertyHook()
        ) || (
            $token->inParameterList()
            && !$token->sinceStatement()->hasOneOf(\T_VARIABLE)
        ) || (
            ($prev = $token->skipPrevSiblingFrom($this->Idx->ValueType))->id === \T_COLON
            && ($prev = $prev->PrevSibling)
            && ($prev = $prev->PrevSibling)
            && $prev->skipPrevSiblingFrom($this->Idx->Ampersand)->id === \T_FN
        );
    }
}
