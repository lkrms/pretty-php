<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Internal;

use Lkrms\PrettyPHP\Token;

/**
 * @internal
 */
final class TokenIndentDelta
{
    public int $PreIndent;
    public int $Indent;
    public int $Deindent;
    public int $HangingIndent;
    public int $LinePadding;
    public int $LineUnpadding;

    /**
     * Get the difference in indentation between two tokens
     *
     * @return self The result of subtracting `$token1`'s indentation and line
     * padding values from `$token2`'s.
     */
    public static function between(Token $token1, Token $token2): self
    {
        $delta = new self();
        $delta->PreIndent = $token2->PreIndent - $token1->PreIndent;
        $delta->Indent = $token2->Indent - $token1->Indent;
        $delta->Deindent = $token2->Deindent - $token1->Deindent;
        $delta->HangingIndent = $token2->HangingIndent - $token1->HangingIndent;
        $delta->LinePadding = $token2->LinePadding - $token1->LinePadding;
        $delta->LineUnpadding = $token2->LineUnpadding - $token1->LineUnpadding;

        return $delta;
    }

    /**
     * Apply the difference in indentation to a token
     */
    public function apply(Token $token): void
    {
        $token->PreIndent += $this->PreIndent;
        $token->Indent += $this->Indent;
        $token->Deindent += $this->Deindent;
        $token->HangingIndent += $this->HangingIndent;
        $token->LinePadding += $this->LinePadding;
        $token->LineUnpadding += $this->LineUnpadding;
    }

    private function __construct() {}
}
