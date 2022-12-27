<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Facade\Env;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;

class SimplifyStrings implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if (!$token->is(T_CONSTANT_ENCAPSED_STRING)) {
            return;
        }

        // \x00 -> \t, \v, \f, \x0e -> \x1f is effectively \x00 -> \x1f without
        // LF (\n) or CR (\r), which aren't escaped unless already escaped
        $escape = "\x00..\t\v\f\x0e..\x1f\"\$\\";
        $match  = '';

        if (!$token->hasNewline()) {
            $escape .= "\n\r";
            $match  .= '\n\r';
        }

        if (Env::isLocaleUtf8()) {
            // Don't escape UTF-8 leading bytes (\xc2 -> \xf4) or continuation
            // bytes (\x80 -> \xbf)
            $escape .= "\x7f\xc0\xc1\xf5..\xff";
            $match  .= '\x7f\xc0\xc1\xf5-\xff';
        } else {
            $escape .= "\x7f..\xff";
            $match  .= '\x7f-\xff';
        }

        $string = '';
        eval("\$string = {$token->Code};");
        $double = $this->doubleQuote($string, $escape);
        if (preg_match("/[\\x00-\\t\\v\\f\\x0e-\\x1f{$match}]/", $string)) {
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
