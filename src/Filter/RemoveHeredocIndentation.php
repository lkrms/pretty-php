<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Token\Token;
use Lkrms\Utility\Pcre;

/**
 * Remove indentation from heredocs
 */
final class RemoveHeredocIndentation implements Filter
{
    use ExtensionTrait;

    public function filterTokens(array $tokens): array
    {
        /** @var array<int,Token[]> */
        $heredocTokens = [];
        /** @var array<int,string[]> */
        $heredocText = [];
        /** @var array<int,Token> */
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
            if (!Pcre::match('/^\h+/', end($heredoc), $matches)) {
                continue;
            }

            // Remove it from the collected tokens
            $stripped = Pcre::replace("/\\n{$matches[0]}/", "\n", $heredoc);

            // And from the start of the first token and closing identifier,
            // where there is no leading newline
            switch ($count = count($heredoc)) {
                case 1:
                    $stripped[0] =
                        Pcre::replace(
                            "/^{$matches[0]}/",
                            '',
                            $stripped[0]
                        );
                    break;

                default:
                    $j = $count - 1;
                    [$stripped[0], $stripped[$j]] =
                        Pcre::replace(
                            "/^{$matches[0]}/",
                            '',
                            [$stripped[0], $stripped[$j]]
                        );
                    break;
            }

            // Finally, update each token
            foreach ($stripped as $j => $code) {
                $heredocTokens[$i][$j]->setText($code);
            }
        }

        return $tokens;
    }
}
