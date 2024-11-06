<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Token;
use Salient\Utility\Str;

/**
 * Assign the starting column of each token to its $column property
 */
final class CollectColumn implements Filter
{
    use ExtensionTrait;

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        if (!(reset($tokens) instanceof Token)) {
            // @codeCoverageIgnoreStart
            return $tokens;
            // @codeCoverageIgnoreEnd
        }

        /** @var Token[] $tokens */
        $last = array_key_last($tokens);
        $column = 1;
        foreach ($tokens as $i => $token) {
            $token->column = $column;
            $text =
                !$this->Idx->Expandable[$token->id]
                || strpos($token->text, "\t") === false
                    ? $token->text
                    : Str::expandTabs($token->text, $this->Formatter->TabSize, $column);
            if ($text !== $token->text) {
                $token->ExpandedText = $text;
            }
            if ($i === $last) {
                break;
            }
            if (($pos = mb_strrpos($text, "\n")) === false) {
                $column += mb_strlen($text);
                continue;
            }
            $column = mb_strlen($text) - $pos;
        }

        return $tokens;
    }
}
