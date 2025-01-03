<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;

/**
 * Apply indentation to heredocs and nowdocs
 *
 * @api
 */
final class HeredocIndentation implements TokenRule
{
    use TokenRuleTrait;

    /** @var Token[] */
    private array $Heredocs;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 900,
            self::BEFORE_RENDER => 900,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(TokenIndex $idx): array
    {
        return [
            \T_START_HEREDOC => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->Heredocs = [];
    }

    /**
     * Apply the rule to the given tokens
     *
     * If `HeredocIndent` has a value other than `NONE`, heredocs are saved for
     * later processing.
     */
    public function processTokens(array $tokens): void
    {
        if ($this->Formatter->HeredocIndent !== HeredocIndent::NONE) {
            $this->Heredocs = $tokens;
        }
    }

    /**
     * Apply the rule to the given tokens
     *
     * The indentation of the first inner token of each heredoc saved earlier is
     * applied to the heredoc by adding whitespace after newline characters in
     * each of its tokens.
     *
     * Whitespace added to each heredoc is also applied to the `HeredocIndent`
     * property of its `T_START_HEREDOC` token, which allows inherited
     * indentation to be removed when processing nested heredocs.
     */
    public function beforeRender(array $tokens): void
    {
        if (!$this->Heredocs) {
            return;
        }

        foreach ($this->Heredocs as $heredoc) {
            $inherited = '';
            $t = $heredoc;
            while ($t = $t->Heredoc) {
                $inherited .= $t->HeredocIndent;
            }

            /** @var Token */
            $next = $heredoc->Next;
            $padding = $next->LinePadding - $next->LineUnpadding;
            if ($this->Formatter->Tab === "\t" && $padding) {
                $indent = $next->renderIndent(true);
                if ($inherited !== '' && $inherited[0] === "\t") {
                    $inherited = str_replace("\t", $this->Formatter->SoftTab, $inherited);
                }
            } else {
                $indent = $next->renderIndent();
            }
            $indent .= $padding
                ? str_repeat(' ', $padding)
                : '';

            if ($inherited !== '') {
                $indent = substr($indent, strlen($inherited));
            }

            $heredoc->HeredocIndent = $indent;

            if ($indent === '') {
                continue;
            }

            $t = $heredoc;
            do {
                $t->setText(str_replace("\n", "\n" . $indent, $t->text));
            } while (
                !($t->id === \T_END_HEREDOC && $t->Heredoc === $heredoc)
                && ($t = $t->Next)
            );
        }
    }
}
