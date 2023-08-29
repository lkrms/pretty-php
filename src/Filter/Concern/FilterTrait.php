<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter\Concern;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Extends Lkrms\PrettyPHP\Concern\ExtensionTrait for use by filters
 *
 */
trait FilterTrait
{
    use ExtensionTrait;

    /**
     * @var Token[]
     */
    protected $Tokens;

    /**
     * Get the previous code token for the token at a given index in the Tokens
     * array
     */
    protected function prevCode(int $i): ?Token
    {
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($this->TypeIndex->NotCode[$token->id]) {
                continue;
            }
            return $token;
        }
        return null;
    }

    /**
     * True if the token at a given index in the Tokens array, together with
     * previous tokens in the same statement, form a declaration of one of the
     * given types
     */
    protected function isDeclarationOf(int $i, int $type, int ...$types): bool
    {
        array_unshift($types, $type);
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($this->TypeIndex->NotCode[$token->id]) {
                continue;
            }
            if (!$this->TypeIndex->DeclarationPart[$token->id]) {
                break;
            }
            if ($token->is($types)) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if the token at a given index in the Tokens array is a comment that
     * starts with '//' or '#'
     */
    protected function isOneLineComment(int $i): bool
    {
        $token = $this->Tokens[$i];
        return $token->id === T_COMMENT &&
            preg_match('@^(?://|#)@', $token->text);
    }
}
