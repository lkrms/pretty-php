<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\TokenUtil;
use Salient\Utility\Exception\ShouldNotHappenException;
use Salient\Utility\Regex;

/**
 * Evaluate strings for comparison
 *
 * @api
 */
final class EvaluateStrings implements Filter
{
    use ExtensionTrait;

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        $stack = [];
        $lastString = null;

        $string = '';
        foreach ($tokens as $token) {
            if ($this->Idx->StringDelimiter[$token->id]) {
                if (!$lastString) {
                    $stack[] = $token;
                    $lastString = $token;
                    continue;
                }
                array_pop($stack);
                $lastString = null;
                continue;
            }

            if ($this->Idx->OpenBracket[$token->id]) {
                $stack[] = $token;
                $lastString = null;
                continue;
            }

            if ($this->Idx->CloseBracket[$token->id]) {
                array_pop($stack);
                $end = end($stack);
                if ($end && $this->Idx->StringDelimiter[$end->id]) {
                    $lastString = $end;
                }
                continue;
            }

            if ($token->id === \T_CONSTANT_ENCAPSED_STRING) {
                eval("\$string = {$token->text};");
            } elseif ($token->id !== \T_ENCAPSED_AND_WHITESPACE) {
                continue;
            } elseif (!$lastString) {
                // @codeCoverageIgnoreStart
                throw new ShouldNotHappenException('Error parsing string');
                // @codeCoverageIgnoreEnd
            } elseif ($lastString->id === \T_DOUBLE_QUOTE) {
                eval("\$string = \"{$token->text}\";");
            } elseif ($lastString->id === \T_BACKTICK) {
                $text = TokenUtil::unescapeBackticks($token->text);
                eval("\$string = \"{$text}\";");
            } elseif ($lastString->id === \T_START_HEREDOC) {
                $start = trim($lastString->text);
                // Ignore nowdocs
                if (substr($start, 0, 4) === "<<<'") {
                    continue;
                }
                $end = Regex::replace('/[^a-zA-Z0-9_]+/', '', $start);
                eval("\$string = {$start}\n{$token->text}\n{$end};");
            } else {
                // @codeCoverageIgnoreStart
                throw new ShouldNotHappenException('Error parsing string');
                // @codeCoverageIgnoreEnd
            }
            $token->text = $string;
        }

        return $tokens;
    }
}
