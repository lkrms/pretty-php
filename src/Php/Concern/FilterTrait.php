<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Concern;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\NavigableToken as Token;

trait FilterTrait
{
    /**
     * @var Token[]
     */
    protected $Tokens;

    /**
     * @var Formatter
     */
    protected $Formatter;

    public function __construct(Formatter $formatter)
    {
        $this->Formatter = $formatter;
    }

    protected function prevCode(int $i, ?int &$prev_i = null): Token
    {
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($token->is(TokenType::NOT_CODE)) {
                continue;
            }
            $prev_i = $i;

            return $token;
        }

        return Token::null();
    }

    /**
     * @param int|string ...$types
     */
    protected function prevDeclarationOf(int $i, ...$types): Token
    {
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($token->is(TokenType::NOT_CODE)) {
                continue;
            }
            if (!$token->is(TokenType::DECLARATION_PART)) {
                break;
            }
            if ($token->is($types)) {
                return $token;
            }
        }

        return Token::null();
    }

    protected function isOneLineComment(int $i): bool
    {
        $token = $this->Tokens[$i];

        return $token->id === T_COMMENT && preg_match('@^(//|#)@', $token->text);
    }

    public function reset(): void {}
}
