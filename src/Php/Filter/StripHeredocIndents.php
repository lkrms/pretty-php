<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Contract\TokenFilter;

/**
 * Remove indentation from heredocs
 *
 */
final class StripHeredocIndents implements TokenFilter
{
    public function __invoke(array $tokens): array
    {
        $heredoc = null;
        foreach ($tokens as $token) {
            if (is_null($heredoc)) {
                if ($token->id === T_START_HEREDOC) {
                    $heredoc = [];
                }
                continue;
            }

            // Collect references to the content of tokens in the heredoc
            $heredoc[] = &$token->text;
            if ($token->id !== T_END_HEREDOC) {
                continue;
            }

            // Check for indentation to remove
            if (!preg_match('/^\h+/', $token->text, $matches)) {
                $heredoc = null;
                continue;
            }

            // The pattern below won't match the first line or closing
            // identifier unless newlines are added temporarily
            $keys = [0];
            if (count($heredoc) > 1) {
                $keys[] = count($heredoc) - 1;
            }
            foreach ($keys as $key) {
                $heredoc[$key] = "\n" . $heredoc[$key];
            }
            // TODO: replace temporary newlines with a better pattern?
            $stripped = preg_replace("/\\n{$matches[0]}/", "\n", $heredoc);
            foreach ($keys as $key) {
                $stripped[$key] = substr($stripped[$key], 1);
            }
            foreach ($heredoc as $i => &$code) {
                $code = $stripped[$i];
            }
            unset($code);

            $heredoc = null;
        }

        return $tokens;
    }
}
