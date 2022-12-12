<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;

class SimplifyStrings extends AbstractTokenRule
{
    public function __invoke(Token $token, int $stage): void
    {
        if (!$token->is(T_CONSTANT_ENCAPSED_STRING)) {
            return;
        }

        // \x00 -> \t, \v, \f, \x0e -> \x1f is effectively \x00 -> \x1f without
        // LF (\n) or CR (\r), which aren't escaped unless already escaped
        $escape = "\x00..\t\v\f\x0e..\x1f\x7f..\xff\"\$\\";
        $match  = '';

        if (!$token->hasNewline()) {
            $escape .= "\n\r";
            $match   = '\n\r';
        }

        $string = '';
        eval("\$string = {$token->Code};");
        $double = $this->doubleQuote($string, $escape);
        if (preg_match("/[\\x00-\\t\\v\\f\\x0e-\\x1f\\x7f-\\xff{$match}]/", $string)) {
            $token->Code = $double;

            return;
        }
        $single = $this->singleQuote($string);
        // '\Lkrms\\' looks invalid and "\\Lkrms\\" uses double quotes
        // unnecessarily, so try '\\Lkrms\\' before giving up on single quotes
        if (!$this->checkConsistency($single) && $this->checkConsistency($double)) {
            $single = preg_replace('/(?<!\\\\)\\\\(?!\\\\)/', '\\\\$0', $single);
        }
        $token->Code = (strlen($single) <= strlen($double) &&
            ($this->checkConsistency($single) || !$this->checkConsistency($double)))
            ? $single
            : $double;
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
        return '"' . preg_replace_callback(
            '/\\\\(?:(?P<octal>[0-7]{3})|.)/',
            fn(array $matches) => ($matches['octal'] ?? null)
                ? sprintf('\x%02x', octdec($matches['octal']))
                : $matches[0],
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
