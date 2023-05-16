<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule\Extra;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Suppress newlines between arguments in function declarations
 *
 */
final class DeclareArgumentsOnOneLine implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 100;
    }

    public function getTokenTypes(): array
    {
        return [
            T['('],
        ];
    }

    public function processToken(Token $token): void
    {
        if (!($token->isDeclaration(T_FUNCTION) ||
            ($this->Formatter->ClosuresAreDeclarations &&
                $token->prevCode()->is([T_FN, T_FUNCTION])))) {
            return;
        }

        $allLines = ~WhitespaceType::BLANK & ~WhitespaceType::LINE;

        $token->WhitespaceMaskNext &= $allLines;
        $token->ClosedBy->WhitespaceMaskPrev &= $allLines;

        $token->inner()->forEach(
            function (Token $t) use ($allLines) {
                $t->WhitespaceMaskPrev &= $allLines;
                $t->WhitespaceMaskNext &= $allLines;
            }
        );
    }
}
