<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token\Concern;

use Lkrms\Exception\Exception;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Token\Token;
use LogicException;

trait ContextAwareTokenTrait
{
    public ?int $SubType = null;

    /**
     * Get the sub-type of a T_COLON token
     *
     * @return TokenSubType::COLON_*
     */
    final public function getColonType(): int
    {
        /** @var Token $this */
        if ($this->id !== T_COLON) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Not a T_COLON');
            // @codeCoverageIgnoreEnd
        }

        if ($this->SubType !== null) {
            /** @var TokenSubType::COLON_* */
            $type = $this->SubType;
            return $type;
        }

        if (!$this->_prevCode) {
            // @codeCoverageIgnoreStart
            throw new Exception('Illegal T_COLON context');
            // @codeCoverageIgnoreEnd
        }

        if ($this->startsAlternativeSyntax()) {
            $this->SubType = TokenSubType::COLON_ALT_SYNTAX_DELIMITER;
        } elseif ($this->inLabel()) {
            $this->SubType = TokenSubType::COLON_LABEL_DELIMITER;
        } elseif ($this->inSwitchCase()) {
            $this->SubType = TokenSubType::COLON_SWITCH_CASE_DELIMITER;
        } elseif (
            $this->_prevCode->id === T_STRING &&
            $this->_prevCode->_prevCode &&
            $this->_prevCode->_prevCode->id === T_ENUM
        ) {
            $this->SubType = TokenSubType::COLON_BACKED_ENUM_TYPE_DELIMITER;
        } elseif ($this->_prevCode->id === T_CLOSE_PARENTHESIS) {
            $prev = $this->_prevCode->_prevSibling;
            if (
                $prev &&
                $prev->id === T_USE &&
                $prev->_prevCode &&
                $prev->_prevCode->id === T_CLOSE_PARENTHESIS
            ) {
                $prev = $prev->_prevCode->_prevSibling;
            }
            if ($prev) {
                $prev = $prev->skipPrevSiblingsOf(
                    T_STRING, T_READONLY, ...TokenType::AMPERSAND
                );
                if ($prev->id === T_FUNCTION || $prev->id === T_FN) {
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
        if ($this->id !== T_QUESTION) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Not a T_QUESTION');
            // @codeCoverageIgnoreEnd
        }

        if ($this->SubType !== null) {
            /** @var TokenSubType::QUESTION_* */
            $type = $this->SubType;
            return $type;
        }

        if (!$this->_prevCode) {
            // @codeCoverageIgnoreStart
            throw new Exception('Illegal T_QUESTION context');
            // @codeCoverageIgnoreEnd
        }

        if ($this->_prevCode->id === T_CONST) {
            $this->SubType = TokenSubType::QUESTION_NULLABLE;
        } elseif ($this->_prevCode->id === T_COLON) {
            $prevType = $this->_prevCode->getColonType();
            if (
                $prevType === TokenSubType::COLON_RETURN_TYPE_DELIMITER ||
                $prevType === TokenSubType::COLON_BACKED_ENUM_TYPE_DELIMITER
            ) {
                $this->SubType = TokenSubType::QUESTION_NULLABLE;
            }
        } elseif (
            $this->_prevCode->is([T_VAR, ...TokenType::KEYWORD_MODIFIER])
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
     * True if the token is in a label
     *
     * Returns `true` if the token is a `T_STRING` or `T_COLON` comprising part
     * of a label.
     */
    final public function inLabel(): bool
    {
        /** @var Token $this */

        // Exclude named arguments
        if ($this->Parent && $this->Parent->id === T_OPEN_PARENTHESIS) {
            return false;
        }

        if (
            $this->id === T_COLON &&
            $this->_prevCode &&
            $this->_prevCode->id === T_STRING &&
            (!$this->_prevCode->_prevSibling ||
                $this->_prevCode->_prevSibling->EndStatement ===
                    $this->_prevCode->_prevSibling)
        ) {
            return true;
        }

        if (
            $this->id === T_STRING &&
            $this->_nextCode->id === T_COLON &&
            (!$this->_prevSibling ||
                $this->_prevSibling->EndStatement ===
                    $this->_prevSibling)
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
        if ($this->id !== T_OPEN_PARENTHESIS) {
            return false;
        }

        if (!$this->_prevCode) {
            return false;
        }

        $prev = $this->_prevCode->skipPrevSiblingsOf(
            T_STRING,
            T_READONLY,
            ...TokenType::AMPERSAND,
        );

        if ($prev->id === T_FUNCTION || $prev->id === T_FN) {
            return true;
        }

        return false;
    }

    /**
     * True if the token is in a parameter list
     */
    final public function inParameterList(): bool
    {
        if ($this->Parent && $this->Parent->isParameterList()) {
            return true;
        }

        return false;
    }

    /**
     * True if the token is in a T_SWITCH case list
     */
    final public function inSwitchCaseList(): bool
    {
        /** @var Token $this */
        if (
            $this->Parent &&
            $this->Parent->_prevSibling &&
            $this->Parent->_prevSibling->_prevSibling &&
            $this->Parent->_prevSibling->_prevSibling->id === T_SWITCH
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
     */
    final public function inSwitchCase(): bool
    {
        /** @var Token $this */
        if (!$this->inSwitchCaseList()) {
            return false;
        }

        if ($this->id === T_CASE || $this->id === T_DEFAULT) {
            return true;
        }

        $lastCaseOrDelimiter = $this->prevSiblingOf(
            T_CASE,
            T_DEFAULT,
            T_COLON,
            T_SEMICOLON,
            T_CLOSE_TAG,
        );

        if (
            $lastCaseOrDelimiter->id === T_CASE ||
            $lastCaseOrDelimiter->id === T_DEFAULT
        ) {
            return true;
        }

        return false;
    }
}
