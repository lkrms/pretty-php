<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;

class ReindentHeredocs implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if (!$token->HeredocOpenedBy ||
                !($indent = $token->HeredocOpenedBy->renderIndent())) {
            return;
        }

        $token->Code = str_replace("\n", "\n" . $indent, $token->Code);
    }
}
