<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token;

use Lkrms\PrettyPHP\Catalog\TokenSubType;

trait ContextAwareTokenTrait
{
    use NavigableTokenTrait;

    /**
     * @var TokenSubType::*|-1|null
     */
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
        if (isset($this->SubType)) {
            return $this->SubType;
        }

        /** @var static&GenericToken $this */
        $method = [
            \T_COLON => 'getColonType',
            \T_QUESTION => 'getQuestionType',
            \T_USE => 'getUseType',
        ][$this->id] ?? null;

        if ($method === null) {
            return $this->SubType = -1;
        }

        // If the method returns `null` because it's too early to determine the
        // token's sub-type, save `null` to ensure the method is called again,
        // but return `-1`
        return ($this->SubType = $this->$method()) ?? -1;
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
}
