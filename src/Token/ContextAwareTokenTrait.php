<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Support\TokenCollection;

trait ContextAwareTokenTrait
{
    use NavigableTokenTrait;

    /** @var TokenSubType::*|-1|null */
    public ?int $SubType = null;

    /**
     * True if the token is the colon before an alternative syntax block
     */
    final public function isColonAltSyntaxDelimiter(): bool
    {
        return $this->getSubType() === TokenSubType::COLON_ALT_SYNTAX_DELIMITER;
    }

    /**
     * True if the token is the colon after a switch case or a label
     */
    final public function isColonStatementDelimiter(): bool
    {
        return $this->getSubType() === TokenSubType::COLON_SWITCH_CASE_DELIMITER
            || $this->SubType === TokenSubType::COLON_LABEL_DELIMITER;
    }

    /**
     * True if the token is the colon before a type declaration
     */
    final public function isColonTypeDelimiter(): bool
    {
        return $this->getSubType() === TokenSubType::COLON_RETURN_TYPE_DELIMITER
            || $this->SubType === TokenSubType::COLON_BACKED_ENUM_TYPE_DELIMITER;
    }

    /**
     * Get the sub-type of a T_COLON, T_QUESTION or T_USE token
     *
     * @return TokenSubType::*|-1
     */
    final public function getSubType(): int
    {
        /** @var static&GenericToken $this */
        if (isset($this->SubType)) {
            return $this->SubType;
        }

        switch ($this->id) {
            case \T_COLON:
                // If it's too early to determine the token's sub-type, save
                // `null` to resolve it later and return `-1`
                return ($this->SubType = $this->getColonType()) ?? -1;
            case \T_QUESTION:
                return $this->SubType = $this->getQuestionType();
            case \T_USE:
                return $this->SubType = $this->getUseType();
            default:
                return $this->SubType = -1;
        }
    }

    /**
     * @return TokenSubType::*|null
     */
    private function getColonType(): ?int
    {
        /** @var static&GenericToken $this */
        if (!$this->PrevCode) {
            $this->throw();
        }

        $prevCode = $this->PrevCode;

        if (
            $this->ClosedBy
            || $this->TypeIndex->AltSyntaxContinueWithoutExpression[$prevCode->id]
            || (
                $prevCode->id === \T_CLOSE_PARENTHESIS
                && $prevCode->PrevSibling
                && (
                    $this->TypeIndex->AltSyntaxStart[$prevCode->PrevSibling->id]
                    || $this->TypeIndex->AltSyntaxContinueWithExpression[$prevCode->PrevSibling->id]
                )
            )
        ) {
            return TokenSubType::COLON_ALT_SYNTAX_DELIMITER;
        }

        if (
            $this->Parent
            && $this->Parent->id === \T_OPEN_PARENTHESIS
            && $this->TypeIndex->MaybeReserved[$prevCode->id]
            && $prevCode->PrevCode
            && (
                $prevCode->PrevCode === $this->Parent
                || $prevCode->PrevCode->id === \T_COMMA
            )
        ) {
            return TokenSubType::COLON_NAMED_ARGUMENT_DELIMITER;
        }

        if ($this->inSwitchCase()) {
            return TokenSubType::COLON_SWITCH_CASE_DELIMITER;
        }

        if (
            $prevCode->id === \T_STRING
            && $prevCode->PrevCode
            && $prevCode->PrevCode->id === \T_ENUM
        ) {
            return TokenSubType::COLON_BACKED_ENUM_TYPE_DELIMITER;
        }

        if ($prevCode->id === \T_CLOSE_PARENTHESIS) {
            $prev = $prevCode->PrevSibling;
            if (
                $prev
                && $prev->id === \T_USE
                && $prev->PrevCode
                && $prev->PrevCode->id === \T_CLOSE_PARENTHESIS
            ) {
                $prev = $prev->PrevCode->PrevSibling;
            }

            if ($prev) {
                $prev = $prev->skipPrevSiblingsFrom($this->TypeIndex->FunctionIdentifier);

                if ($prev->id === \T_FUNCTION || $prev->id === \T_FN) {
                    return TokenSubType::COLON_RETURN_TYPE_DELIMITER;
                }
            }
        }

        // The remaining possibilities require statements to have been parsed
        if ($prevCode->PrevSibling && !$prevCode->PrevSibling->EndStatement) {
            return null;
        }

        if (
            $prevCode->id === \T_STRING && (
                !$prevCode->PrevSibling || (
                    $prevCode->PrevSibling->EndStatement
                    && $prevCode->PrevSibling->EndStatement->NextSibling === $prevCode
                )
            )
        ) {
            return TokenSubType::COLON_LABEL_DELIMITER;
        }

        return TokenSubType::COLON_TERNARY_OPERATOR;
    }

    /**
     * @return TokenSubType::*
     */
    private function getQuestionType(): int
    {
        /** @var static&GenericToken $this */
        if (!$this->PrevCode) {
            $this->throw();
        }

        $prevCode = $this->PrevCode;
        if (
            $prevCode->id === \T_CONST
            || ($prevCode->id === \T_COLON && $prevCode->isColonTypeDelimiter())
            || $this->TypeIndex->VarOrModifier[$prevCode->id]
            || $this->inParameterList()
        ) {
            return TokenSubType::QUESTION_NULLABLE;
        }

        return TokenSubType::QUESTION_TERNARY_OPERATOR;
    }

    /**
     * @return TokenSubType::*
     */
    private function getUseType(): int
    {
        if ($this->PrevCode && $this->PrevCode->id === \T_CLOSE_PARENTHESIS) {
            return TokenSubType::USE_VARIABLES;
        }

        if ($this->Parent && $this->Parent->id === \T_OPEN_BRACE) {
            $t = $this->Parent->PrevSibling;
            while ($t && $this->TypeIndex->DeclarationPart[$t->id]) {
                if ($this->TypeIndex->DeclarationClass[$t->id]) {
                    return TokenSubType::USE_TRAIT;
                }
                $t = $t->PrevSibling;
            }
        }

        return TokenSubType::USE_IMPORT;
    }

    /**
     * True if the token is in a parameter list
     */
    final public function inParameterList(): bool
    {
        /** @var static&GenericToken $this */
        if ($this->Parent && $this->Parent->isParameterList()) {
            return true;
        }

        return false;
    }

    /**
     * True if the token encloses a parameter list
     */
    final public function isParameterList(): bool
    {
        /** @var static&GenericToken $this */
        if ($this->id !== \T_OPEN_PARENTHESIS || !$this->PrevCode) {
            return false;
        }

        $prev = $this->PrevCode->skipPrevSiblingsFrom($this->TypeIndex->FunctionIdentifier);

        if ($prev->id === \T_FUNCTION || $prev->id === \T_FN) {
            return true;
        }

        return false;
    }

    /**
     * True if the token is the opening brace of a function
     */
    final public function isFunctionBrace(bool $allowAnonymous = true): bool
    {
        /** @var static&GenericToken $this */
        if ($this->id !== \T_OPEN_BRACE || !$this->PrevCode) {
            return false;
        }

        $prev = $this->PrevCode;
        if ($prev->id !== \T_CLOSE_PARENTHESIS) {
            $prev = $prev->skipPrevSiblingsFrom($this->TypeIndex->ValueType);
            if (
                $prev->id === \T_COLON
                && $prev->PrevCode
                && $prev->PrevCode->id === \T_CLOSE_PARENTHESIS
            ) {
                $prev = $prev->PrevCode;
            } else {
                return false;
            }
        }

        $prev = $prev->PrevSibling;
        if (
            $prev
            && $prev->id === \T_USE
            && $prev->PrevCode
            && $prev->PrevCode->id === \T_CLOSE_PARENTHESIS
        ) {
            $prev = $prev->PrevCode->PrevSibling;
        }

        if (!$prev || (
            !$allowAnonymous && (
                $prev->id === \T_FUNCTION
                || $this->TypeIndex->Ampersand[$prev->id]
            )
        )) {
            return false;
        }

        $prev = $prev->skipPrevSiblingsFrom($this->TypeIndex->FunctionIdentifier);

        return $prev->id === \T_FUNCTION;
    }

    /**
     * True if the token is in a T_CASE or T_DEFAULT statement in a T_SWITCH
     *
     * Returns `true` if the token is `T_CASE` or `T_DEFAULT`, part of the
     * expression after `T_CASE`, or the subsequent `:` or `;` delimiter.
     */
    final public function inSwitchCase(): bool
    {
        /** @var static&GenericToken $this */
        return
            $this->inSwitchCaseList() && (
                $this->id === \T_CASE
                || $this->id === \T_DEFAULT
                || (($prev = $this->prevSiblingFrom($this->TypeIndex->SwitchCaseOrDelimiter)->orNull())
                    && ($prev->id === \T_CASE || $prev->id === \T_DEFAULT))
            );
    }

    /**
     * True if the token is in a T_SWITCH case list
     */
    final public function inSwitchCaseList(): bool
    {
        /** @var static&GenericToken $this */
        return
            $this->Parent
            && $this->Parent->PrevSibling
            && $this->Parent->PrevSibling->PrevSibling
            && $this->Parent->PrevSibling->PrevSibling->id === \T_SWITCH;
    }

    /**
     * True if the token belongs to a declaration
     */
    final public function inDeclaration(bool $allowAnonymous = true): bool
    {
        return $this->skipPrevSiblingsToDeclarationStart()
                    ->isDeclaration($allowAnonymous);
    }

    /**
     * True if a declaration starts at the token and is not an anonymous
     * function or class
     *
     * @phpstan-assert-if-true TokenCollection $parts
     */
    final public function isNamedDeclaration(?TokenCollection &$parts = null): bool
    {
        return func_num_args() > 0
            ? $this->doIsDeclaration(false, $parts)
            : $this->doIsDeclaration(false);
    }

    /**
     * True if a declaration starts at the token
     */
    final public function isDeclaration(bool $allowAnonymous = true): bool
    {
        return $this->doIsDeclaration($allowAnonymous);
    }

    private function doIsDeclaration(
        bool $allowAnonymous,
        ?TokenCollection &$parts = null
    ): bool {
        /** @var Token $this */
        if ($this->Flags & TokenFlag::NAMED_DECLARATION) {
            if (func_num_args() > 1) {
                $parts = $this->namedDeclarationParts();
            }
            return true;
        }

        // Exclude tokens other than the first in a possible declaration
        if ($allowAnonymous) {
            if (
                !$this->Expression || (
                    $this->PrevSibling
                    && $this->PrevSibling->Expression === $this->Expression
                    && $this->TypeIndex->DeclarationPartWithNewAndBody[$this->PrevSibling->id]
                )
            ) {
                return false;
            }
        } elseif ($this->Statement !== $this) {
            return false;
        }

        // Get the first non-attribute
        $first = $this->skipSiblingsFrom($this->TypeIndex->Attribute);

        // Exclude non-declarations
        if (!$this->TypeIndex->Declaration[$first->id]) {
            return false;
        }

        /** @var Token */
        $next = $first->NextCode;

        // Exclude:
        // - `static` outside declarations
        // - `case` in switch statements
        // - `namespace` in relative names
        // - promoted constructor parameters
        if (
            ($first->id === \T_STATIC && !($next->id === \T_VARIABLE || $this->TypeIndex->Declaration[$next->id]))
            || ($first->id === \T_CASE && $first->inSwitchCaseList())
            || ($first->id === \T_NAMESPACE && $next->id === \T_NS_SEPARATOR)
            || ($this->TypeIndex->VisibilityWithReadonly[$first->id] && $first->inParameterList())
        ) {
            return false;
        }

        if ($allowAnonymous) {
            return true;
        }

        $parts = $this->namedDeclarationParts();
        if (!$parts->count()) {
            return false;
        }

        // @phpstan-ignore-next-line
        $this->Flags |= TokenFlag::NAMED_DECLARATION;

        return true;
    }

    /**
     * Get the first token in the sequence of declaration parts to which the
     * token belongs, or the token itself
     *
     * The token returned by this method may not be part of a declaration. It
     * should only be used as a starting point for further checks.
     *
     * @return Token
     */
    final public function skipPrevSiblingsToDeclarationStart()
    {
        if (!$this->Expression) {
            return $this;
        }

        $t = $this;
        while (
            $t->PrevSibling
            && $t->PrevSibling->Expression === $this->Expression
            && $this->TypeIndex->DeclarationPartWithNewAndBody[$t->PrevSibling->id]
        ) {
            $t = $t->PrevSibling;
        }
        return $t;
    }
}
