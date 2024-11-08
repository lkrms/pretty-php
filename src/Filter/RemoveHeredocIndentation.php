<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\GenericToken;
use Lkrms\PrettyPHP\Token;
use Salient\Utility\Regex;

/**
 * Remove indentation from heredocs
 */
final class RemoveHeredocIndentation implements Filter
{
    use ExtensionTrait;

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        // Heredocs can be nested, so tokens that belong to inner and outer
        // heredocs are collected multiple times. Text is collected in a
        // separate array with the same structure to ensure the only prefix
        // removed is the innermost one.

        /** @var array<int,GenericToken[]> */
        $heredocTokens = [];
        /** @var array<int,string[]> */
        $heredocText = [];
        /** @var array<int,GenericToken> */
        $stack = [];
        foreach ($tokens as $i => $token) {
            if ($stack) {
                foreach ($stack as $j => $heredoc) {
                    $heredocTokens[$j][] = $token;
                    $heredocText[$j][] = $token->text;
                }
            }
            if ($token->id === \T_START_HEREDOC) {
                $stack[$i] = $token;
                continue;
            }
            if ($token->id === \T_END_HEREDOC) {
                array_pop($stack);
            }
        }

        /** @var array<int,string[]> $heredocText */
        foreach ($heredocText as $i => $heredoc) {
            // Check for indentation to remove
            if (!Regex::match('/^\h++/', end($heredoc), $matches)) {
                continue;
            }

            // Remove it from the collected tokens
            $stripped = Regex::replace("/\\n{$matches[0]}/", "\n", $heredoc);

            // And from the start of the first token and closing identifier,
            // where there is no leading newline and no chance the token is not
            // at the start of a line
            $last = count($heredoc) - 1;
            if ($last === 0) {
                $stripped[0] =
                    Regex::replace(
                        "/^{$matches[0]}/",
                        '',
                        $stripped[0]
                    );
            } else {
                [$stripped[0], $stripped[$last]] =
                    Regex::replace(
                        "/^{$matches[0]}/",
                        '',
                        [$stripped[0], $stripped[$last]]
                    );
            }

            // Finally, update each token
            foreach ($stripped as $j => $code) {
                $token = $heredocTokens[$i][$j];
                if ($token instanceof Token) {
                    $token->setText($code);
                } else {
                    $token->text = $code;
                }
            }
        }

        return $tokens;
    }
}
