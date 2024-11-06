<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Token\GenericToken;

/**
 * @api
 *
 * @phpstan-require-implements Filter
 */
trait FilterTrait
{
    use ExtensionTrait;

    /** @var list<GenericToken> */
    protected array $Tokens;

    /**
     * Get the given token's previous code token
     */
    protected function getPrevCode(int $i, ?int &$key = null): ?GenericToken
    {
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($this->Idx->NotCode[$token->id]) {
                continue;
            }
            $key = $i;
            return $token;
        }
        return null;
    }

    /**
     * Get the given token's previous sibling that is one of the types in an
     * index
     *
     * @param array<int,bool> $index
     */
    protected function getPrevSiblingFrom(int $i, array $index, ?int $to = null, ?int &$key = null): ?GenericToken
    {
        while ($token = $this->getPrevSibling($i, 1, $j)) {
            if ($to !== null && $j < $to) {
                // @codeCoverageIgnoreStart
                break;
                // @codeCoverageIgnoreEnd
            }
            if ($index[$token->id]) {
                $key = $j;
                return $token;
            }
            $i = $j;
        }
        return null;
    }

    /**
     * Get one of the given token's previous siblings
     */
    protected function getPrevSibling(int $i, int $offset = 1, ?int &$key = null): ?GenericToken
    {
        $depth = 0;
        if ($this->Idx->CloseBracket[$this->Tokens[$i]->id]) {
            $depth++;
            $offset++;
        }
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($this->Idx->NotCode[$token->id]) {
                continue;
            } elseif ($this->Idx->CloseBracket[$token->id]) {
                $depth++;
            } elseif ($this->Idx->OpenBracket[$token->id]) {
                $depth--;
                if ($depth < 0) {
                    break;
                }
            }
            if (!$depth) {
                $offset--;
                if (!$offset) {
                    $key = $i;
                    return $token;
                }
            }
        }
        return null;
    }

    /**
     * Get the given token's parent
     */
    protected function getParent(int $i, ?int &$key = null): ?GenericToken
    {
        while ($this->getPrevSibling($i, 1, $j)) {
            $i = $j;
            $token = $this->Tokens[$i];
            if ($token->id === \T_COLON && $this->isColonAltSyntaxDelimiter($i)) {
                $key = $i;
                return $token;
            }
        }
        return $this->getPrevCode($i, $key);
    }

    /**
     * Check if the given T_COLON belongs to an alternative syntax construct
     */
    protected function isColonAltSyntaxDelimiter(int $i): bool
    {
        /** @var GenericToken */
        $token = $this->getPrevCode($i, $j);
        return $this->Idx->AltSyntaxContinueWithoutExpression[$token->id]
            || (
                $token->id === \T_CLOSE_PARENTHESIS
                && ($prev = $this->getPrevSibling($j))
                && (
                    $this->Idx->AltSyntaxStart[$prev->id]
                    || $this->Idx->AltSyntaxContinueWithExpression[$prev->id]
                )
            );
    }

    /**
     * Check if the given T_COLON is a switch case delimiter
     */
    protected function isColonSwitchCaseDelimiter(int $i, ?int $parentIndex = null): bool
    {
        $parent = $parentIndex === null
            // @codeCoverageIgnoreStart
            ? $this->getParent($i, $parentIndex)
            // @codeCoverageIgnoreEnd
            : $this->Tokens[$parentIndex];
        return $parent
            && ($parentPrev = $this->getPrevSibling($parentIndex, 2))
            && $parentPrev->id === \T_SWITCH
            && ($prev = $this->getPrevSiblingFrom($i, $this->Idx->SwitchCaseOrDelimiter, $parentIndex + 1))
            && ($prev->id === \T_CASE || $prev->id === \T_DEFAULT);
    }

    /**
     * Check if the given token, together with previous tokens in the same
     * statement, form a declaration of the given type
     */
    protected function isDeclarationOf(int $i, int $type): bool
    {
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($this->Idx->NotCode[$token->id]) {
                continue;
            }
            if (!$this->Idx->DeclarationPart[$token->id]) {
                return false;
            }
            if ($token->id === $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the given token is a comment that starts with '//' or '#'
     */
    protected function isOneLineComment(int $i): bool
    {
        $token = $this->Tokens[$i];
        return $token->id === \T_COMMENT && (
            $token->text[0] === '#'
            || $token->text[1] === '/'
        );
    }
}
