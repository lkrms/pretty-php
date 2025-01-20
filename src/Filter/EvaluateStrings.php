<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\TokenUtil;
use Salient\Utility\Exception\ShouldNotHappenException;

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
                    // `b"` -> `"`
                    if ($token->id === \T_DOUBLE_QUOTE) {
                        $token->text = '"';
                    }
                    continue;
                }
                // `b<<< "EOF"` -> `<<<EOF`
                if ($lastString->id === \T_START_HEREDOC) {
                    $lastString->text = '<<<' . trim(ltrim($lastString->text, 'bB'), "< \t\"\n\r") . "\n";
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
                $start = rtrim($lastString->text);
                // Ignore nowdocs
                if ($start[-1] === "'") {
                    continue;
                }
                $end = trim(ltrim($start, 'bB'), "< \t\"'");
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
