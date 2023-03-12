<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;

/**
 * Apply indentation to heredocs
 *
 * {@see \Lkrms\Pretty\Php\Formatter} normalises heredocs by removing
 * indentation prior to formatting. At the expense of compatibility with PHP
 * prior to 7.3, this rule [re]applies it.
 */
final class ReindentHeredocs implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @var Token[]
     */
    private $Heredocs = [];

    public function getTokenTypes(): ?array
    {
        return [
            T_START_HEREDOC,
        ];
    }

    public function processToken(Token $token): void
    {
        $this->Heredocs[] = $token;
    }

    public function beforeRender(array $tokens): void
    {
        foreach ($this->Heredocs as $heredoc) {
            $next    = $heredoc->next();
            $indent  = $next->renderIndent();
            $padding = str_repeat(' ', $next->LinePadding - $next->LineUnpadding);
            if (($indent[0] ?? null) === "\t" && $padding) {
                $indent = $next->renderIndent(true);
            }
            $indent .= $padding;
            if (!$indent) {
                continue;
            }
            $heredoc->HeredocIndent = $indent;

            $token = $heredoc;
            do {
                $token->text = str_replace("\n", "\n" . $indent, $token->text);
                if ($token->is(T_END_HEREDOC)) {
                    break;
                }
                $token = $token->next();
            } while (!$token->IsNull);
        }
    }
}
