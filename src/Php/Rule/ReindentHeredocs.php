<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;

class ReindentHeredocs extends AbstractTokenRule
{
    public function __invoke(Token $token, int $stage): void
    {
        if (!$token->HeredocOpenedBy ||
                !($indent = $token->HeredocOpenedBy->indent())) {
            return;
        }

        $token->Code = str_replace("\n", "\n" . $indent, $token->Code);
    }
}
