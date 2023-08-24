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

    protected function prevCode(int $i, ?int &$prev_i = null): ?Token
    {
        $token = $this->Tokens[$i];
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($this->TypeIndex->NotCode[$token->id]) {
                continue;
            }
            $prev_i = $i;

            return $token;
        }

        return null;
    }

    protected function isOneLineComment(int $i): bool
    {
        $token = $this->Tokens[$i];

        return $token->id === T_COMMENT && preg_match('@^(//|#)@', $token->text);
    }
}
