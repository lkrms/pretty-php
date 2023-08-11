<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;

/**
 * Replace single- and double-quoted strings with whichever is clearer and more
 * efficient
 *
 * Single-quoted strings are preferred unless:
 * - one or more characters require a backslash escape;
 * - the double-quoted equivalent is shorter; or
 * - the single-quoted string contains a mix of `\` and `\\` and the
 *   double-quoted equivalent contains one or the other, but not both.
 */
final class NormaliseStrings implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKEN:
                return 60;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
    {
        return [
            T_CONSTANT_ENCAPSED_STRING,
        ];
    }

    public function processToken(Token $token): void
    {
        // \x00 -> \t, \v, \f, \x0e -> \x1f is effectively \x00 -> \x1f without
        // LF (\n) or CR (\r), which aren't escaped unless already escaped
        $escape = "\0..\t\v\f\x0e..\x1f\"\$\\";
        $match = '';

        if (!$token->hasNewline()) {
            $escape .= "\n\r";
            $match .= '\n\r';
        }

        $string = '';
        eval("\$string = {$token->text};");

        // If $string contains valid UTF-8 sequences, don't escape leading bytes
        // (\xc2 -> \xf4) or continuation bytes (\x80 -> \xbf)
        if (mb_check_encoding($string, 'UTF-8')) {
            $escape .= "\x7f\xc0\xc1\xf5..\xff";
            $match .= '\x7f\xc0\xc1\xf5-\xff';
        } else {
            $escape .= "\x7f..\xff";
            $match .= '\x7f-\xff';
        }

        $double = $this->doubleQuote($string, $escape);
        if (preg_match("/[\\x00-\\x09\\x0b\\x0c\\x0e-\\x1f{$match}]/", $string)) {
            $token->setText($double);

            return;
        }
        $single = $this->singleQuote($string);
        // '\Lkrms\\' looks invalid and "\\Lkrms\\" uses double quotes
        // unnecessarily, so try '\\Lkrms\\' before giving up on single quotes
        if (!$this->checkConsistency($single) && $this->checkConsistency($double)) {
            $single = preg_replace('/(?<!\\\\)\\\\(?!\\\\)/', '\\\\$0', $single);
        }
        $token->setText((mb_strlen($single) <= mb_strlen($double) &&
                ($this->checkConsistency($single) || !$this->checkConsistency($double)))
            ? $single
            : $double);
    }

    private function singleQuote(string $string): string
    {
        return "'" . preg_replace(
            "/(?:\\\\(?=\\\\)|(?<=\\\\)\\\\)|\\\\(?='|\$)|'/",
            '\\\\$0',
            $string
        ) . "'";
    }

    private function doubleQuote(string $string, string $escape): string
    {
        // - Recognised by PHP: \0 \e \f \n \r \t \v
        // - Returned by addcslashes: \0 \a \b \f \n \r \t \v
        return '"' . preg_replace_callback(
            '/\\\\(?:(?P<octal>[0-7]{3})|(?P<cslash>[ab])|.)/',
            fn(array $matches) =>
                ($matches['octal'] ?? null)
                    ? (($dec = octdec($matches['octal']))
                        ? sprintf('\x%02x', $dec)
                        : '\0')
                    : (($matches['cslash'] ?? null)
                        ? sprintf('\x%02x', ['a' => 7, 'b' => 8][$matches['cslash']])
                        : $matches[0]),
            addcslashes($string, $escape)
        ) . '"';
    }

    /**
     * Return true if $string contains "\" or "\\", but not both
     *
     * Also returns true if `$string` doesn't contain either sequence.
     */
    private function checkConsistency(string $string): bool
    {
        $singles = preg_match_all('/(?<!\\\\)\\\\(?!\\\\)/', $string);
        $doubles = preg_match_all('/(?<!\\\\)\\\\\\\\(?!\\\\)/', $string);

        return !($singles + $doubles) || ($singles xor $doubles);
    }
}
