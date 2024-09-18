<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Apply indentation to heredocs and nowdocs
 *
 * Indentation is removed from heredocs by a normalisation filter before code is
 * formatted. At the expense of compatibility with PHP versions prior to 7.3,
 * this rule [re]applies it.
 *
 * @api
 */
final class HeredocIndentation implements TokenRule
{
    use TokenRuleTrait;

    /** @var Token[] */
    private array $Heredocs = [];

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 900;

            case self::BEFORE_RENDER:
                return 900;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return [
            \T_START_HEREDOC => true,
        ];
    }

    public function processTokens(array $tokens): void
    {
        if ($this->Formatter->HeredocIndent === HeredocIndent::NONE) {
            return;
        }

        $this->Heredocs = $tokens;
    }

    public function beforeRender(array $tokens): void
    {
        if (!$this->Heredocs) {
            return;
        }

        foreach ($this->Heredocs as $heredoc) {
            $inherited = '';
            $current = $heredoc->Heredoc;
            while ($current) {
                $inherited .= $current->HeredocIndent;
                $current = $current->Heredoc;
            }

            $next = $heredoc->Next;
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
                if ($current->id === \T_END_HEREDOC && $current->Heredoc === $heredoc) {
                    break;
                }
            } while ($current = $current->Next);
        }
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->Heredocs = [];
    }
}
