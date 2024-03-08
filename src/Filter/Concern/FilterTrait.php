<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter\Concern;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Token\GenericToken;

trait FilterTrait
{
    use ExtensionTrait;

    /**
     * @var GenericToken[]
     */
    protected array $Tokens;

    /**
     * Check if the given tokens are instances of the given class
     *
     * @template T of GenericToken
     *
     * @param GenericToken[] $tokens
     * @param class-string<T> $class
     * @phpstan-assert-if-true T[] $tokens
     */
    protected function isArrayOf(array $tokens, string $class): bool
    {
        return $tokens && reset($tokens) instanceof $class;
    }

    /**
     * Get the given token's previous code token
     */
    protected function prevCode(int $i): ?GenericToken
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
     * statement, form a declaration of the given type
     */
    protected function isDeclarationOf(int $i, int $type): bool
    {
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($this->TypeIndex->NotCode[$token->id]) {
                continue;
            }
            if (!$this->TypeIndex->DeclarationPart[$token->id]) {
                return false;
            }
            if ($token->id === $type) {
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
        return $token->id === \T_COMMENT && (
            $token->text[0] === '#' ||
            $token->text[1] === '/'
        );
    }
}
