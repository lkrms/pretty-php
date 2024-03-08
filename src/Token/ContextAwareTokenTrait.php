<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token;

use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Catalog\TokenType;
use LogicException;
use PhpToken;

trait ContextAwareTokenTrait
{
    use NavigableTokenTrait;

    /**
     * @var TokenSubType::*|-1|null
     */
    public ?int $SubType = null;

    /**
     * True if the token is the colon after a switch case or a label
     */
    final public function isColonStatementDelimiter(): bool
    {
        /** @var static&PhpToken $this */
        return $this->id === \T_COLON && (
            $this->getSubType() === TokenSubType::COLON_SWITCH_CASE_DELIMITER ||
            $this->SubType === TokenSubType::COLON_LABEL_DELIMITER
        );
    }

    /**
     * True if the token is the colon before a type declaration
     */
    final public function isColonTypeDelimiter(): bool
    {
        /** @var static&PhpToken $this */
        return $this->id === \T_COLON && (
            $this->getSubType() === TokenSubType::COLON_RETURN_TYPE_DELIMITER ||
            $this->SubType === TokenSubType::COLON_BACKED_ENUM_TYPE_DELIMITER
        );
    }

    /**
     * Get the sub-type of a T_COLON, T_QUESTION or T_USE token
     *
     * @return TokenSubType::*|-1
     */
    final public function getSubType(): int
    {
        /** @var static&PhpToken $this */
        return $this->SubType ??= (
            $this->id === \T_COLON
                ? $this->getColonType()
                : ($this->id === \T_QUESTION
                    ? $this->getQuestionType()
                    : ($this->id === \T_USE
                        ? $this->getUseType()
                        : -1))
        );
    }

    /**
     * @return TokenSubType::*
     */
    private function getColonType(): int
    {
        /** @var static&PhpToken $this */
        if ($this->startsAlternativeSyntax()) {
            return TokenSubType::COLON_ALT_SYNTAX_DELIMITER;
        }

        if ($this->inLabel()) {
            return TokenSubType::COLON_LABEL_DELIMITER;
        }

        if ($this->inSwitchCase()) {
            return TokenSubType::COLON_SWITCH_CASE_DELIMITER;
        }

        if (
            $this->PrevCode->id === \T_STRING &&
            $this->PrevCode->PrevCode &&
            $this->PrevCode->PrevCode->id === \T_ENUM
        ) {
            return TokenSubType::COLON_BACKED_ENUM_TYPE_DELIMITER;
        }

        if ($this->PrevCode->id === \T_CLOSE_PARENTHESIS) {
            $prev = $this->PrevCode->PrevSibling;
            if (
                $prev &&
                $prev->id === \T_USE &&
                $prev->PrevCode &&
                $prev->PrevCode->id === \T_CLOSE_PARENTHESIS
            ) {
                $prev = $prev->PrevCode->PrevSibling;
            }

            if ($prev) {
                $prev = $prev->skipPrevSiblingsOf(
                    \T_STRING,
                    \T_READONLY,
                    ...TokenType::AMPERSAND,
                );

                if ($prev->id === \T_FUNCTION || $prev->id === \T_FN) {
                    return TokenSubType::COLON_RETURN_TYPE_DELIMITER;
                }
            }
        }

        return TokenSubType::COLON_TERNARY_OPERATOR;
    }

    /**
     * @return TokenSubType::*
     */
    private function getQuestionType(): int
    {
        /** @var static&PhpToken $this */
        if (!$this->PrevCode) {
            throw new LogicException('Invalid T_QUESTION');
        }

        /** @var static&PhpToken $prevCode */
        $prevCode = $this->PrevCode;
        if (
            $prevCode->id === \T_CONST ||
            ($prevCode->id === \T_COLON && $prevCode->isColonTypeDelimiter()) ||
            $this->TypeIndex->VarOrModifier[$this->PrevCode->id] ||
            $this->inParameterList()
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

        if (!$this->Parent || $this->Parent->id !== \T_OPEN_BRACE) {
            return TokenSubType::USE_IMPORT;
        }

        $t = $this->Parent->PrevSibling;
        while ($t && $this->TypeIndex->DeclarationPart[$t->id]) {
            if ($this->TypeIndex->DeclarationClass[$t->id]) {
                return TokenSubType::USE_TRAIT;
            }
            $t = $t->PrevSibling;
        }

        return TokenSubType::USE_IMPORT;
    }

    /**
     * True if the token is the colon at the start of an alternative syntax
     * block
     */
    final public function startsAlternativeSyntax(): bool
    {
        /** @var static&PhpToken $this */
        if ($this->id !== \T_COLON) {
            return false;
        }

        if (
            $this->ClosedBy ||
            $this->TypeIndex->AltSyntaxContinueWithoutExpression[$this->PrevCode->id]
        ) {
            return true;
        }

        if ($this->PrevCode->id !== \T_CLOSE_PARENTHESIS) {
            return false;
        }

        $prev = $this->PrevCode->PrevSibling;
        if (
            $this->TypeIndex->AltSyntaxStart[$prev->id] ||
            $this->TypeIndex->AltSyntaxContinueWithExpression[$prev->id]
        ) {
            return true;
        }

        return false;
    }

    /**
     * True if the token is in a label
     *
     * Returns `true` if the token is a `T_STRING` or `T_COLON` comprising part
     * of a label.
     *
     * @see ContextAwareTokenTrait::getColonType()
     */
    final protected function inLabel(): bool
    {
        // Exclude named arguments
        /** @var static&PhpToken $this */
        if ($this->Parent && $this->Parent->id === \T_OPEN_PARENTHESIS) {
            return false;
        }

        if (
            $this->id === \T_COLON &&
            $this->PrevCode &&
            $this->PrevCode->id === \T_STRING && (
                !$this->PrevCode->PrevSibling || (
                    $this->PrevCode->PrevSibling->EndStatement &&
                    $this->PrevCode->PrevSibling->EndStatement->NextSibling === $this->PrevCode
                )
            )
        ) {
            return true;
        }

        if (
            $this->id === \T_STRING &&
            $this->NextCode->id === \T_COLON &&
            (!$this->PrevSibling ||
                ($this->PrevSibling->EndStatement &&
                    $this->PrevSibling->EndStatement->NextSibling === $this))
        ) {
            return true;
        }

        return false;
    }

    /**
     * True if the token encloses a parameter list
     */
    final public function isParameterList(): bool
    {
        /** @var static&PhpToken $this */
        if ($this->id !== \T_OPEN_PARENTHESIS) {
            return false;
        }

        if (!$this->PrevCode) {
            return false;
        }

        $prev = $this->PrevCode->skipPrevSiblingsOf(
            \T_STRING,
            \T_READONLY,
            ...TokenType::AMPERSAND,
        );

        if ($prev->id === \T_FUNCTION || $prev->id === \T_FN) {
            return true;
        }

        return false;
    }

    /**
     * True if the token is in a parameter list
     */
    final public function inParameterList(): bool
    {
        /** @var static&PhpToken $this */
        if ($this->Parent && $this->Parent->isParameterList()) {
            return true;
        }

        return false;
    }

    /**
     * True if the token is in a T_SWITCH case list
     */
    final protected function inSwitchCaseList(): bool
    {
        /** @var static&PhpToken $this */
        if (
            $this->Parent &&
            $this->Parent->PrevSibling &&
            $this->Parent->PrevSibling->PrevSibling &&
            $this->Parent->PrevSibling->PrevSibling->id === \T_SWITCH
        ) {
            return true;
        }

        return false;
    }

    /**
     * True if the token is in a T_CASE or T_DEFAULT statement in a T_SWITCH
     *
     * Returns `true` if the token is `T_CASE` or `T_DEFAULT`, part of the
     * expression after `T_CASE`, or the subsequent `:` or `;` delimiter.
     *
     * @see ContextAwareTokenTrait::getColonType()
     */
    final protected function inSwitchCase(): bool
    {
        /** @var static&PhpToken $this */
        if (!$this->inSwitchCaseList()) {
            return false;
        }

        if ($this->id === \T_CASE || $this->id === \T_DEFAULT) {
            return true;
        }

        $lastCaseOrDelimiter = $this->prevSiblingOf(
            \T_CASE,
            \T_DEFAULT,
            \T_COLON,
            \T_SEMICOLON,
            \T_CLOSE_TAG,
        );

        if (
            $lastCaseOrDelimiter->id === \T_CASE ||
            $lastCaseOrDelimiter->id === \T_DEFAULT
        ) {
            return true;
        }

        return false;
    }
}
