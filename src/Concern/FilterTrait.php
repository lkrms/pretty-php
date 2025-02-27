<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\GenericToken;

/**
 * @api
 *
 * @phpstan-require-implements Filter
 */
trait FilterTrait
{
    use ExtensionTrait;

    /** @var list<GenericToken> */
    private array $Tokens;

    /**
     * Get the given token's previous code token
     *
     * @param-out int $key
     */
    private function getPrevCode(int $i, ?int &$key = null): ?GenericToken
    {
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($this->Idx->NotCode[$token->id]) {
                continue;
            }
            $key = $i;
            return $token;
        }
        $key = -1;
        return null;
    }

    /**
     * Get the given token's previous sibling that is in an index
     *
     * @param array<int,bool> $index
     * @param-out int $key
     */
    private function getPrevSiblingFrom(int $i, array $index, ?int $to = null, ?int &$key = null): ?GenericToken
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
        // @codeCoverageIgnoreStart
        $key = -1;
        return null;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get one of the given token's previous siblings
     *
     * @param-out int $key
     */
    private function getPrevSibling(int $i, int $offset = 1, ?int &$key = null): ?GenericToken
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
        $key = -1;
        return null;
    }

    /**
     * Get the given token's next sibling that is in an index
     *
     * @param array<int,bool> $index
     * @param-out int $key
     */
    private function getNextSiblingFrom(int $i, array $index, int $to, ?int &$key = null): ?GenericToken
    {
        while ($token = $this->getNextSibling($i, $to + 1, 1, $j)) {
            if ($index[$token->id]) {
                $key = $j;
                return $token;
            }
            $i = $j;
        }
        $key = -1;
        return null;
    }

    /**
     * Get the given token's next sibling with the given token ID
     *
     * @param-out int $key
     */
    private function getNextSiblingOf(int $i, int $id, int $to, ?int &$key = null): ?GenericToken
    {
        while ($token = $this->getNextSibling($i, $to + 1, 1, $j)) {
            if ($token->id === $id) {
                $key = $j;
                return $token;
            }
            $i = $j;
        }
        // @codeCoverageIgnoreStart
        $key = -1;
        return null;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get one of the given token's next siblings
     *
     * @param-out int $key
     */
    private function getNextSibling(int $i, int $count, int $offset = 1, ?int &$key = null): ?GenericToken
    {
        $depth = 0;
        while ($i + 1 < $count) {
            $token = $this->Tokens[$i];
            if ($this->Idx->OpenBracket[$token->id]) {
                $depth++;
            } elseif ($this->Idx->CloseBracket[$token->id]) {
                $depth--;
                if ($depth < 0) {
                    // @codeCoverageIgnoreStart
                    break;
                    // @codeCoverageIgnoreEnd
                }
            }
            $token = $this->Tokens[++$i];
            while ($this->Idx->NotCode[$token->id]) {
                if ($i + 1 < $count) {
                    $token = $this->Tokens[++$i];
                } else {
                    // @codeCoverageIgnoreStart
                    break 2;
                    // @codeCoverageIgnoreEnd
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
        // @codeCoverageIgnoreStart
        $key = -1;
        return null;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the given token's parent
     *
     * @param-out int $key
     */
    private function getParent(int $i, ?int &$key = null): ?GenericToken
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
    private function isColonAltSyntaxDelimiter(int $i): bool
    {
        /** @var GenericToken */
        $token = $this->getPrevCode($i, $j);
        return $token->id === \T_ELSE
            || (
                $token->id === \T_CLOSE_PARENTHESIS
                && ($prev = $this->getPrevSibling($j))
                && $this->Idx->AltStartOrContinue[$prev->id]
            );
    }

    /**
     * Check if the given T_COLON is a switch case delimiter
     */
    private function isColonSwitchCaseDelimiter(int $i, ?int $parentIndex = null): bool
    {
        $parent = $parentIndex === null
            ? $this->getParent($i, $parentIndex)
            : $this->Tokens[$parentIndex];

        if (
            !$parent
            || !($parentPrev = $this->getPrevSibling($parentIndex, 2))
            || $parentPrev->id !== \T_SWITCH
        ) {
            return false;
        }

        $t = $this->getPrevSiblingFrom($i, $this->Idx->CaseOrDefault, $parentIndex + 1, $j);
        if (!$t) {
            return false;
        }

        $ternaryCount = 0;
        do {
            $t = $this->getNextSiblingFrom($j, $this->Idx->SwitchCaseDelimiterOrTernary, $i, $j);
            if (!$t) {
                return false;
            } elseif ($t->id === \T_QUESTION) {
                /** @var GenericToken */
                $prev = $this->getPrevCode($j, $prevIndex);
                if (
                    $prev->id !== \T_COLON
                    || !$this->isColonReturnTypeDelimiter($prevIndex)
                ) {
                    $ternaryCount++;
                }
                continue;
            } elseif ($t->id === \T_COLON) {
                if (
                    $this->isColonReturnTypeDelimiter($j)
                    || $ternaryCount--
                ) {
                    continue;
                }
            }
            break;
        } while (true);

        return $t === $this->Tokens[$i];
    }

    /**
     * Check if the given T_COLON is a return type delimiter
     */
    private function isColonReturnTypeDelimiter(int $i, ?int $prevCodeIndex = null): bool
    {
        /** @var GenericToken */
        $prevCode = $prevCodeIndex === null
            ? $this->getPrevCode($i, $prevCodeIndex)
            : $this->Tokens[$prevCodeIndex];

        if ($prevCode->id === \T_CLOSE_PARENTHESIS) {
            $prev = $this->getPrevSibling($prevCodeIndex, 1, $prevIndex);
            if (
                $prev
                && $prev->id === \T_USE
                && ($prevCode2 = $this->getPrevCode($prevIndex, $prevCode2Index))
                && $prevCode2->id === \T_CLOSE_PARENTHESIS
            ) {
                $prev = $this->getPrevSibling($prevCode2Index, 1, $prevIndex);
            }

            while ($prev && $this->Idx->FunctionIdentifier[$prev->id]) {
                $prev = $this->getPrevSibling($prevIndex, 1, $prevIndex);
            }

            if ($prev && $this->Idx->FunctionOrFn[$prev->id]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the given token, together with previous tokens in the same
     * statement, form a declaration with the given token ID
     */
    private function isDeclarationOf(int $i, int $id): bool
    {
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($this->Idx->NotCode[$token->id]) {
                continue;
            }
            if (!$this->Idx->DeclarationPart[$token->id]) {
                return false;
            }
            if ($token->id === $id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the given token is a comment that starts with '//' or '#'
     */
    private function isOneLineComment(int $i): bool
    {
        $token = $this->Tokens[$i];
        return $token->id === \T_COMMENT && (
            $token->text[0] === '#'
            || $token->text[1] === '/'
        );
    }
}
