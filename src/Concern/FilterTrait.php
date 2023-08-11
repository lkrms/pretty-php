<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use PhpToken;

trait FilterTrait
{
    use ExtensionTrait;

    /**
     * @var PhpToken[]
     */
    protected $Tokens;

    protected function prevCode(int $i, ?int &$prev_i = null): ?PhpToken
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
