<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Internal;

use Lkrms\PrettyPHP\Token;

/**
 * The difference in indentation between two tokens
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
     * @return self The result of subtracting `$token1`'s indentation levels and
     * padding values from `$token2`'s.
     */
    public static function between(Token $token1, Token $token2): self
    {
        $instance = new self();
        $instance->PreIndent = $token2->PreIndent - $token1->PreIndent;
        $instance->Indent = $token2->Indent - $token1->Indent;
        $instance->Deindent = $token2->Deindent - $token1->Deindent;
        $instance->HangingIndent = $token2->HangingIndent - $token1->HangingIndent;
        $instance->LinePadding = $token2->LinePadding - $token1->LinePadding;
        $instance->LineUnpadding = $token2->LineUnpadding - $token1->LineUnpadding;

        return $instance;
    }

    /**
     * Apply the difference in indentation to a token and return it
     */
    public function apply(Token $token): Token
    {
        $token->PreIndent += $this->PreIndent;
        $token->Indent += $this->Indent;
        $token->Deindent += $this->Deindent;
        $token->HangingIndent += $this->HangingIndent;
        $token->LinePadding += $this->LinePadding;
        $token->LineUnpadding += $this->LineUnpadding;

        return $token;
    }

    private function __construct() {}
}
