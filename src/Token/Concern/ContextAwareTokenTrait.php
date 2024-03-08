<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token\Concern;

use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Token\Token;
use LogicException;

trait ContextAwareTokenTrait
{
    public ?int $SubType = null;

    /**
     * True if the token is a colon after a switch case or a label
     */
    final public function isColonStatementDelimiter(): bool
    {
        /** @var Token $this */
        return $this->id === \T_COLON && (
            $this->getColonType() === TokenSubType::COLON_SWITCH_CASE_DELIMITER ||
            $this->SubType === TokenSubType::COLON_LABEL_DELIMITER
        );
    }

    /**
     * Get the sub-type of a T_COLON token
     *
     * @return TokenSubType::COLON_*
     */
    final public function getColonType(): int
    {
        /** @var Token $this */
        if ($this->id !== \T_COLON) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Not a T_COLON');
            // @codeCoverageIgnoreEnd
        }

        if ($this->SubType !== null) {
            /** @var TokenSubType::COLON_* */
            $type = $this->SubType;
            return $type;
        }

        if (!$this->PrevCode) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Illegal T_COLON context');
            // @codeCoverageIgnoreEnd
        }

        if ($this->startsAlternativeSyntax()) {
            $this->SubType = TokenSubType::COLON_ALT_SYNTAX_DELIMITER;
        } elseif ($this->inLabel()) {
            $this->SubType = TokenSubType::COLON_LABEL_DELIMITER;
        } elseif ($this->inSwitchCase()) {
            $this->SubType = TokenSubType::COLON_SWITCH_CASE_DELIMITER;
        } elseif (
            $this->PrevCode->id === \T_STRING &&
            $this->PrevCode->PrevCode &&
            $this->PrevCode->PrevCode->id === \T_ENUM
        ) {
            $this->SubType = TokenSubType::COLON_BACKED_ENUM_TYPE_DELIMITER;
        } elseif ($this->PrevCode->id === \T_CLOSE_PARENTHESIS) {
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
                    \T_STRING, \T_READONLY, ...TokenType::AMPERSAND
                );
                if ($prev->id === \T_FUNCTION || $prev->id === \T_FN) {
                    $this->SubType = TokenSubType::COLON_RETURN_TYPE_DELIMITER;
                }
            }
        }

        if ($this->SubType === null) {
            $this->SubType = TokenSubType::COLON_TERNARY_OPERATOR;
        }

        return $this->SubType;
    }

    /**
     * Get the sub-type of a T_QUESTION token
     *
     * @return TokenSubType::QUESTION_*
     */
    final public function getQuestionType(): int
    {
        /** @var Token $this */
        if ($this->id !== \T_QUESTION) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Not a T_QUESTION');
            // @codeCoverageIgnoreEnd
        }

        if ($this->SubType !== null) {
            /** @var TokenSubType::QUESTION_* */
            $type = $this->SubType;
            return $type;
        }

        if (!$this->PrevCode) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Illegal T_QUESTION context');
            // @codeCoverageIgnoreEnd
        }

        if ($this->PrevCode->id === \T_CONST) {
            $this->SubType = TokenSubType::QUESTION_NULLABLE;
        } elseif ($this->PrevCode->id === \T_COLON) {
            $prevType = $this->PrevCode->getColonType();
            if (
                $prevType === TokenSubType::COLON_RETURN_TYPE_DELIMITER ||
                $prevType === TokenSubType::COLON_BACKED_ENUM_TYPE_DELIMITER
            ) {
                $this->SubType = TokenSubType::QUESTION_NULLABLE;
            }
        } elseif (
            $this->PrevCode->is([\T_VAR, ...TokenType::KEYWORD_MODIFIER])
        ) {
            $this->SubType = TokenSubType::QUESTION_NULLABLE;
        } elseif ($this->inParameterList()) {
            $this->SubType = TokenSubType::QUESTION_NULLABLE;
        }

        if ($this->SubType === null) {
            $this->SubType = TokenSubType::QUESTION_TERNARY_OPERATOR;
        }

        return $this->SubType;
    }

    /**
     * Get the sub-type of a T_USE token
     *
     * @return TokenSubType::USE_*
     */
    public function getUseType(): int
    {
        /** @var Token $this */
        if ($this->id !== \T_USE) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Not a T_USE');
            // @codeCoverageIgnoreEnd
        }

        if ($this->SubType !== null) {
            /** @var TokenSubType::USE_* */
            $type = $this->SubType;
            return $type;
        }

        if (
            $this->PrevCode &&
            $this->PrevCode->id === \T_CLOSE_PARENTHESIS
        ) {
            $this->SubType = TokenSubType::USE_VARIABLES;
        } elseif (
            !$this->Parent ||
            $this->Parent->id !== \T_OPEN_BRACE
        ) {
            $this->SubType = TokenSubType::USE_IMPORT;
        } else {
            $t = $this->Parent->PrevSibling;
            while (
                $t &&
                $this->TypeIndex->DeclarationPart[$t->id]
            ) {
                if ($this->TypeIndex->DeclarationClass[$t->id]) {
                    $this->SubType = TokenSubType::USE_TRAIT;
                    break;
                }
                $t = $t->PrevSibling;
            }
        }

        if ($this->SubType === null) {
            $this->SubType = TokenSubType::USE_IMPORT;
        }

        return $this->SubType;
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
        /** @var Token $this */
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
        /** @var Token $this */
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
        /** @var Token $this */
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
        /** @var Token $this */
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
        /** @var Token $this */
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
