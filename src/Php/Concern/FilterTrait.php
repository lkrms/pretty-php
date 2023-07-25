<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Concern;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\NavigableToken as Token;
use Lkrms\Pretty\Php\Support\TokenTypeIndex;

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

    /**
     * @var TokenTypeIndex
     */
    protected $TypeIndex;

    public function __construct(Formatter $formatter)
    {
        $this->Formatter = $formatter;
        $this->TypeIndex = $formatter->TokenTypeIndex;
    }

    protected function prevCode(int $i, ?int &$prev_i = null): Token
    {
        $token = $this->Tokens[$i];
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($token->is(TokenType::NOT_CODE)) {
                continue;
            }
            $prev_i = $i;

            return $token;
        }

        return $token->null();
    }

    protected function isOneLineComment(int $i): bool
    {
        $token = $this->Tokens[$i];

        return $token->id === T_COMMENT && preg_match('@^(//|#)@', $token->text);
    }

    public function reset(): void {}
}
