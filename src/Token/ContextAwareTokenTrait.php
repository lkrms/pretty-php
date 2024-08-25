<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Support\TokenCollection;

trait ContextAwareTokenTrait
{
    use NavigableTokenTrait;

    /** @var TokenSubType::*|-1|null */
    public ?int $SubType = null;

    /**
     * Check if the token is the colon before an alternative syntax block
     */
    public function isColonAltSyntaxDelimiter(): bool
    {
        return $this->getSubType() === TokenSubType::COLON_ALT_SYNTAX_DELIMITER;
    }

    /**
     * Check if the token is the colon after a switch case or a label
     */
    public function isColonStatementDelimiter(): bool
    {
        return $this->getSubType() === TokenSubType::COLON_SWITCH_CASE_DELIMITER
            || $this->SubType === TokenSubType::COLON_LABEL_DELIMITER;
    }

    /**
     * Check if the token is the colon before a type declaration
     */
    public function isColonTypeDelimiter(): bool
    {
        return $this->getSubType() === TokenSubType::COLON_RETURN_TYPE_DELIMITER
            || $this->SubType === TokenSubType::COLON_BACKED_ENUM_TYPE_DELIMITER;
    }

    /**
     * Get the sub-type of a T_COLON, T_QUESTION or T_USE token
     *
     * @return TokenSubType::*|-1
     */
    public function getSubType(): int
    {
        if ($this->SubType !== null) {
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
        if (!$this->PrevCode) {
            $this->throw();
        }

        $prevCode = $this->PrevCode;

        if (
            $this->ClosedBy
            || $this->Idx->AltSyntaxContinueWithoutExpression[$prevCode->id]
            || (
                $prevCode->id === \T_CLOSE_PARENTHESIS
                && $prevCode->PrevSibling
                && (
                    $this->Idx->AltSyntaxStart[$prevCode->PrevSibling->id]
                    || $this->Idx->AltSyntaxContinueWithExpression[$prevCode->PrevSibling->id]
                )
            )
        ) {
            return TokenSubType::COLON_ALT_SYNTAX_DELIMITER;
        }

        if (
            $this->Parent
            && $this->Parent->id === \T_OPEN_PARENTHESIS
            && $this->Idx->MaybeReserved[$prevCode->id]
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
                $prev = $prev->skipPrevSiblingsFrom($this->Idx->FunctionIdentifier);

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
        if (!$this->PrevCode) {
            $this->throw();
        }

        $prevCode = $this->PrevCode;
        if (
            $prevCode->id === \T_CONST
            || ($prevCode->id === \T_COLON && $prevCode->isColonTypeDelimiter())
            || $this->Idx->VarOrModifier[$prevCode->id]
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
            while ($t && $this->Idx->DeclarationPart[$t->id]) {
                if ($this->Idx->DeclarationClass[$t->id]) {
                    return TokenSubType::USE_TRAIT;
                }
                $t = $t->PrevSibling;
            }
        }

        return TokenSubType::USE_IMPORT;
    }

    /**
     * Check if the token is in a parameter list
     */
    public function inParameterList(): bool
    {
        return $this->Parent && $this->Parent->isParameterList();
    }

    /**
     * Check if the token encloses a parameter list
     */
    public function isParameterList(): bool
    {
        if ($this->id !== \T_OPEN_PARENTHESIS || !$this->PrevCode) {
            return false;
        }

        $prev = $this->PrevCode->skipPrevSiblingsFrom($this->Idx->FunctionIdentifier);

        if ($prev->id === \T_FUNCTION || $prev->id === \T_FN) {
            return true;
        }

        return false;
    }

    /**
     * Check if the token is the opening brace of a function
     */
    public function isFunctionBrace(bool $allowAnonymous = true): bool
    {
        if ($this->id !== \T_OPEN_BRACE || !$this->PrevCode) {
            return false;
        }

        $prev = $this->PrevCode;
        if ($prev->id !== \T_CLOSE_PARENTHESIS) {
            $prev = $prev->skipPrevSiblingsFrom($this->Idx->ValueType);
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
                || $this->Idx->Ampersand[$prev->id]
            )
        )) {
            return false;
        }

        $prev = $prev->skipPrevSiblingsFrom($this->Idx->FunctionIdentifier);

        return $prev->id === \T_FUNCTION;
    }

    /**
     * Check if the token is in a T_CASE or T_DEFAULT statement in a T_SWITCH
     *
     * Returns `true` if the token is `T_CASE` or `T_DEFAULT`, part of the
     * expression after `T_CASE`, or the subsequent `:` or `;` delimiter.
     */
    public function inSwitchCase(): bool
    {
        return $this->inSwitchCaseList() && (
            $this->id === \T_CASE
            || $this->id === \T_DEFAULT
            || ($prev = $this->prevSiblingFrom($this->Idx->SwitchCaseOrDelimiter))->id === \T_CASE
            || $prev->id === \T_DEFAULT
        );
    }

    /**
     * Check if the token is in a T_SWITCH case list
     */
    public function inSwitchCaseList(): bool
    {
        return
            $this->Parent
            && $this->Parent->PrevSibling
            && $this->Parent->PrevSibling->PrevSibling
            && $this->Parent->PrevSibling->PrevSibling->id === \T_SWITCH;
    }

    /**
     * Check if the token is part of a non-anonymous declaration
     */
    public function inNamedDeclaration(): bool
    {
        return $this->skipPrevSiblingsToDeclarationStart()
                    ->doIsDeclaration(false);
    }

    /**
     * Check if the token is part of a declaration
     */
    public function inDeclaration(): bool
    {
        return $this->skipPrevSiblingsToDeclarationStart()
                    ->doIsDeclaration(true);
    }

    /**
     * Check if the token is the first in a non-anonymous declaration
     *
     * @phpstan-assert-if-true TokenCollection $parts
     */
    public function isNamedDeclaration(?TokenCollection &$parts = null): bool
    {
        return $this->doIsDeclaration(false, $parts);
    }

    /**
     * Check if the token is the first in a declaration
     */
    public function isDeclaration(): bool
    {
        return $this->doIsDeclaration(true);
    }

    private function doIsDeclaration(
        bool $allowAnonymous,
        ?TokenCollection &$parts = null
    ): bool {
        if ($this->Flags & TokenFlag::NAMED_DECLARATION) {
            $parts = $this->Data[TokenData::NAMED_DECLARATION_PARTS];
            return true;
        }

        // Exclude tokens other than the first in a declaration
        if ($allowAnonymous) {
            if (!$this->Expression || (
                $this->PrevSibling
                && $this->PrevSibling->Expression === $this->Expression
                && $this->Idx->DeclarationPartWithNewAndBody[$this->PrevSibling->id]
            )) {
                return false;
            }
        } elseif ($this->Statement !== $this) {
            return false;
        }

        // Get the first non-attribute
        $first = $this->skipSiblingsFrom($this->Idx->Attribute);

        // Exclude non-declarations
        if (!$this->Idx->Declaration[$first->id]) {
            return false;
        }

        /** @var Token */
        $next = $first->NextCode;

        // Exclude:
        // - `static` outside declarations
        // - `case` in switch statements
        // - promoted constructor parameters
        if (
            (
                $first->id === \T_STATIC
                && !$this->Idx->Declaration[$next->id]  // `static function`
                && !(                                   // `static $foo` in a property context
                    $next->id === \T_VARIABLE
                    && $first->Parent
                    && $first->Parent->id === \T_OPEN_BRACE
                    && $first->Parent
                             ->skipPrevSiblingsToDeclarationStart()
                             ->collectSiblings($first->Parent)
                             ->hasOneFrom($this->Idx->DeclarationClass)
                )
                && !(                                   // `static int $foo`
                    $this->Idx->ValueTypeStart[$next->id]
                    && $next->skipSiblingsFrom($this->Idx->ValueType)->id === \T_VARIABLE
                )
            )
            || ($first->id === \T_CASE && $first->inSwitchCaseList())
            || ($this->Idx->VisibilityWithReadonly[$first->id] && $first->inParameterList())
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

        // @phpstan-ignore assign.propertyType
        $this->Flags |= TokenFlag::NAMED_DECLARATION;
        $this->Data[TokenData::NAMED_DECLARATION_PARTS] = $parts;

        return true;
    }

    /**
     * Get the first token in the sequence of declaration parts to which the
     * token belongs, or the token itself
     *
     * The token returned by this method may not be part of a declaration. It
     * should only be used as a starting point for further checks.
     *
     * @api
     *
     * @return static
     */
    public function skipPrevSiblingsToDeclarationStart()
    {
        if (!$this->Expression) {
            return $this;
        }

        $t = $this;
        while (
            $t->PrevSibling
            && $t->PrevSibling->Expression === $this->Expression
            && $this->Idx->DeclarationPartWithNewAndBody[$t->PrevSibling->id]
        ) {
            $t = $t->PrevSibling;
        }
        /** @var static */
        return $t;
    }
}
