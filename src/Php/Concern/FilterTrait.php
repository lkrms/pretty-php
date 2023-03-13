<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Concern;

use Lkrms\Pretty\Php\NullToken;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

trait FilterTrait
{
    /**
     * @var Token[]
     */
    private $Tokens;

    private function prevCode(int $i, ?int &$prev_i = null): Token
    {
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($token->is(TokenType::NOT_CODE)) {
                continue;
            }
            $prev_i = $i;

            return $token;
        }

        return NullToken::create();
    }

    /**
     * @param int|string ...$types
     */
    private function prevDeclarationOf(int $i, ...$types): Token
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

        return NullToken::create();
    }

    public function destroy(): void
    {
        unset($this->Tokens);
    }
}
