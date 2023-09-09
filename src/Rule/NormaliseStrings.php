<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;
use Lkrms\Utility\Pcre;

/**
 * Normalise escape sequences in strings and replace single- and double-quoted
 * strings with whichever syntax is most readable and economical
 *
 * Single-quoted strings are preferred unless one or more characters require
 * escaping, or the double-quoted equivalent is shorter.
 *
 * @api
 */
final class NormaliseStrings implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
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

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // \0 -> \t, \v, \f, \x0e -> \x1f is effectively \0 -> \x1f without
            // LF (\n) or CR (\r), which aren't escaped unless already escaped
            $escape = "\0..\t\v\f\x0e..\x1f\"\$\\";
            $match = '';

            if (!$token->hasNewline()) {
                $escape .= "\n\r";
                $match .= '\n\r';
            }

            $string = '';
            eval("\$string = {$token->text};");
            $doubleQuote = '"';

            // If $string contains valid UTF-8 sequences, don't escape leading
            // bytes (\xc2 -> \xf4) or continuation bytes (\x80 -> \xbf)
            if (mb_check_encoding($string, 'UTF-8')) {
                $escape .= "\x7f\xc0\xc1\xf5..\xff";
                $match .= '\x7f\xc0\xc1\xf5-\xff';
            } else {
                $escape .= "\x7f..\xff";
                $match .= '\x7f-\xff';
            }

            // Convert octal notation to hexadecimal (e.g. "\177" to "\x7f") and
            // correct for differences between C and PHP escape sequences:
            // - recognised by PHP: \0 \e \f \n \r \t \v
            // - applied by addcslashes: \000 \033 \a \b \f \n \r \t \v
            $double = $doubleQuote . Pcre::replaceCallback(
                '/((?<!\\\\)(?:\\\\\\\\)*)\\\\(?:(?P<octal>[0-7]{3})|(?P<cslash>[ab]))/',
                fn(array $matches) =>
                    $matches[1] . ($matches['octal'] !== null
                        ? (($dec = octdec($matches['octal']))
                            ? ($dec === 27 ? '\e' : sprintf('\x%02x', $dec))
                            : '\0')
                        : sprintf('\x%02x', ['a' => 7, 'b' => 8][$matches['cslash']])),
                addcslashes($string, $escape),
                -1,
                $count,
                PREG_UNMATCHED_AS_NULL
            ) . $doubleQuote;

            // Use the double-quoted variant if escape sequences remain after
            // unescaping tabs used for indentation
            if ($this->Formatter->Tab === "\t") {
                $double = Pcre::replaceCallback(
                    '/^(?:\\\\t)+(?=\S|$)/m',
                    fn(array $matches) =>
                        str_replace('\t', "\t", $matches[0]),
                    $double,
                );
                if ($token->id !== T_CONSTANT_ENCAPSED_STRING ||
                        Pcre::match("/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f{$match}]/", $string) ||
                        Pcre::match('/(?<!\\\\)(?:\\\\\\\\)*\\\\t/', $double)) {
                    $token->setText($double);
                    continue;
                }
            } elseif ($token->id !== T_CONSTANT_ENCAPSED_STRING ||
                    Pcre::match("/[\\x00-\\x09\\x0b\\x0c\\x0e-\\x1f{$match}]/", $string)) {
                $token->setText($double);
                continue;
            }

            $single = "'" . Pcre::replace(
                "/(?:\\\\(?=\\\\)|(?<=\\\\)\\\\)|\\\\(?='|\$)|'/",
                '\\\\$0',
                $string
            ) . "'";

            // '\Lkrms\\' is valid but appears not to be, so replace it with
            // '\\Lkrms\\'
            $singles = Pcre::match('/(?<!\\\\)\\\\(?!\\\\)/', $single);
            $doubles = Pcre::match('/(?<!\\\\)\\\\\\\\/', $single);

            if ($singles && $doubles) {
                $single = Pcre::replace('/(?<!\\\\)\\\\(?!\\\\)/', '\\\\$0', $single);
            }

            if (mb_strlen($single) <= mb_strlen($double)) {
                $token->setText($single);
                continue;
            }

            $token->setText($double);
        }
    }
}
