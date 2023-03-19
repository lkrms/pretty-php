<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\Filter;

/**
 * Remove indentation from heredocs
 *
 */
final class StripHeredocIndents implements Filter
{
    use FilterTrait;

    public function __invoke(array $tokens): array
    {
        $heredoc = null;
        $og      = null;
        foreach ($tokens as $token) {
            if (is_null($heredoc)) {
                if ($token->id === T_START_HEREDOC) {
                    $heredoc = [];
                    $og      = [];
                }
                continue;
            }

            // Collect references to the content of tokens in the heredoc
            $heredoc[] = &$token->text;
            $og[]      = &$token->OriginalText;
            if ($token->id !== T_END_HEREDOC) {
                continue;
            }

            // Check for indentation to remove
            if (!preg_match('/^\h+/', $token->text, $matches)) {
                $heredoc = null;
                $og      = null;
                continue;
            }

            // Remove it from the collected tokens
            $stripped = preg_replace("/\\n{$matches[0]}/", "\n", $heredoc);

            // And from the start of the first token and closing identifier,
            // where there is no leading newline
            switch ($count = count($heredoc)) {
                case 1:
                    $stripped[0] =
                        preg_replace("/^{$matches[0]}/",
                                     '',
                                     $stripped[0]);
                    break;

                default:
                    $i = $count - 1;
                    [$stripped[0], $stripped[$i]] =
                        preg_replace("/^{$matches[0]}/",
                                     '',
                                     [$stripped[0], $stripped[$i]]);
                    break;
            }

            // Finally, update each token
            foreach ($heredoc as $i => &$code) {
                $og[$i] = $og[$i] ?: $code;
                $code   = $stripped[$i];
            }
            unset($code);

            $heredoc = null;
            $og      = null;
        }

        return $tokens;
    }
}
