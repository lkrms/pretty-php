<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter\Concern;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Token\Token;
use Lkrms\Utility\Pcre;

trait FilterTrait
{
    use ExtensionTrait;

    /**
     * @var Token[]
     */
    protected $Tokens;

    /**
     * Get the given token's previous code token
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
     * True if the given token, together with previous tokens in the same
     * statement, form a declaration of one of the given types
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
                return false;
            }
            if ($token->is($types)) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if the given token is a comment that starts with '//' or '#'
     */
    protected function isOneLineComment(int $i): bool
    {
        $token = $this->Tokens[$i];
        return $token->id === \T_COMMENT &&
            Pcre::match('@^(?://|#)@', $token->text);
    }
}
