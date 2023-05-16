<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Token;

/**
 * Apply indentation to heredocs
 *
 * {@see Formatter} normalises heredocs by removing indentation prior to
 * formatting. At the expense of compatibility with PHP prior to 7.3, this rule
 * [re]applies it.
 */
final class ReindentHeredocs implements TokenRule
{
    use TokenRuleTrait {
        destroy as private _destroy;
    }

    /**
     * @var Token[]
     */
    private $Heredocs = [];

    public function getPriority(string $method): ?int
    {
        return 900;
    }

    public function getTokenTypes(): array
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
            $inherited = '';
            $current = $heredoc->HeredocOpenedBy;
            while ($current) {
                $inherited .= $current->HeredocIndent;
                $current = $current->HeredocOpenedBy;
            }

            $next = $heredoc->next();
            $indent = $next->renderIndent();
            $padding = str_repeat(' ', $next->LinePadding - $next->LineUnpadding);
            if (($indent[0] ?? null) === "\t" && $padding) {
                $indent = $next->renderIndent(true);
            }
            if (($inherited[0] ?? null) === "\t" && ($indent[0] ?? null) !== "\t") {
                $inherited = str_replace("\t", $this->Formatter->SoftTab, $inherited);
            }
            $indent .= $padding;
            $indent = substr($indent, strlen($inherited));
            if (!$indent) {
                continue;
            }
            $heredoc->HeredocIndent = $indent;

            $current = $heredoc;
            do {
                $current->setText(str_replace("\n", "\n" . $indent, $current->text));
                if ($current->is(T_END_HEREDOC) && $current->HeredocOpenedBy === $heredoc) {
                    break;
                }
            } while ($current = $current->_next);
        }
    }

    public function destroy(): void
    {
        unset($this->Heredocs);
        $this->_destroy();
    }
}
