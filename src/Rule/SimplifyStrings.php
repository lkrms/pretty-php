<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Exception\RuleException;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Salient\Utility\Regex;

/**
 * Normalise escape sequences in strings, and replace single- and double-quoted
 * strings with the most readable and economical syntax
 *
 * Single-quoted strings are preferred unless one or more characters require
 * escaping, or the double-quoted equivalent is shorter.
 *
 * @api
 */
final class SimplifyStrings implements TokenRule
{
    use TokenRuleTrait;

    private const INVISIBLE = '/^' . Regex::INVISIBLE_CHAR . '$/u';

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 60;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return [
            \T_CONSTANT_ENCAPSED_STRING => true,
            \T_ENCAPSED_AND_WHITESPACE => true,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Ignore nowdocs
            if (
                $token->id !== \T_CONSTANT_ENCAPSED_STRING
                && $token->String->id === \T_START_HEREDOC
                && substr($token->String->text, 0, 4) === "<<<'"
            ) {
                continue;
            }

            // Characters in $escape are backslash-escaped
            $escape = '';

            // Characters in $match trigger suppression of single-quoted syntax
            // when found in a T_CONSTANT_ENCAPSED_STRING
            $match = '';

            // Characters in $reserved have a special meaning after backslash
            $reserved = '';

            // Don't escape line breaks unless they are already escaped
            if (
                !$token->hasNewline() || (
                    $token->Next->id === \T_END_HEREDOC
                    && strpos(substr($token->text, 0, -1), "\n") === false
                )
            ) {
                $escape .= "\n\r";
                $match .= '\n\r';
            } else {
                $escape .= "\r";
                $match .= '\r';
            }

            $string = '';
            $doubleQuote = '';
            $suffix = '';
            if ($token->id === \T_CONSTANT_ENCAPSED_STRING) {
                eval("\$string = {$token->text};");
                $doubleQuote = '"';
                $escape .= '"';
                $reserved .= '"';
            } elseif ($token->String->id === \T_DOUBLE_QUOTE) {
                eval("\$string = \"{$token->text}\";");
                $escape .= '"';
                $reserved .= '"';
            } elseif ($token->String->id === \T_BACKTICK) {
                // Convert backtick-enclosed substrings to double-quoted
                // equivalents by escaping '\"' and '"', and unescaping '\`'
                $text = Regex::replaceCallback(
                    '/((?<!\\\\)(?:\\\\\\\\)*)(\\\\?"|\\\\`)/',
                    fn(array $matches) =>
                        $matches[1]
                            . ($matches[2] === '\"'
                                ? '\\\\\\"'
                                : ($matches[2] === '"'
                                    ? '\"'
                                    : '`')),
                    $token->text
                );
                eval("\$string = \"{$text}\";");
                $escape .= '`';
                $reserved .= '`';
            } elseif ($token->String->id === \T_START_HEREDOC) {
                $start = trim($token->String->text);
                $text = $token->text;
                $end = trim($token->String->StringClosedBy->text);
                if ($token->Next->id === \T_END_HEREDOC) {
                    $text = substr($text, 0, -1);
                    $suffix = "\n";
                }
                eval("\$string = {$start}\n{$text}\n{$end};");
            } else {
                // @codeCoverageIgnoreStart
                throw new RuleException(
                    sprintf('Not a string delimiter: %s', $token->String->getTokenName())
                );
                // @codeCoverageIgnoreEnd
            }

            // If $string contains valid UTF-8 sequences, don't escape leading
            // bytes (\xc2 -> \xf4) or continuation bytes (\x80 -> \xbf)
            $utf8 = false;
            if (mb_check_encoding($string, 'UTF-8')) {
                $escape .= "\x7f\xc0\xc1\xf5..\xff";
                $match .= '\x7f\xc0\xc1\xf5-\xff';
                $utf8 = true;
            } else {
                $escape .= "\x7f..\xff";
                $match .= '\x7f-\xff';
            }

            // \0..\t\v\f\x0e..\x1f is equivalent to \0..\x1f without \n and \r
            $double = addcslashes($string, "\0..\t\v\f\x0e..\x1f\$\\{$escape}");

            // Convert ignorable code points to "\u{xxxx}" unless they belong to
            // an extended grapheme cluster, i.e. a recognised Unicode sequence
            $utf8Escapes = 0;
            if ($utf8) {
                $double = Regex::replaceCallback(
                    '/(?![\x00-\x7f])\X/u',
                    function (array $matches) use (&$utf8Escapes): string {
                        if (!Regex::match(self::INVISIBLE, $matches[0])) {
                            return $matches[0];
                        }
                        $utf8Escapes++;
                        return sprintf('\u{%04X}', mb_ord($matches[0]));
                    },
                    $double,
                );
            }

            // Convert octal notation to hexadecimal (e.g. "\177" to "\x7f") and
            // correct for differences between C and PHP escape sequences:
            // - recognised by PHP: \0 \e \f \n \r \t \v
            // - applied by addcslashes: \000 \033 \a \b \f \n \r \t \v
            $double = Regex::replaceCallback(
                '/((?<!\\\\)(?:\\\\\\\\)*)\\\\(?:(?<NUL>000(?![0-7]))|(?<octal>[0-7]{3})|(?<cslash>[ab]))/',
                fn(array $matches): string =>
                    $matches[1]
                        . ($matches['NUL'] !== null
                            ? '\0'
                            : ($matches['octal'] !== null
                                ? (($dec = octdec($matches['octal'])) === 27
                                    ? '\e'
                                    : sprintf('\x%02x', $dec))
                                : sprintf('\x%02x', ['a' => 7, 'b' => 8][$matches['cslash']]))),
                $double,
                -1,
                $count,
                \PREG_UNMATCHED_AS_NULL
            );

            // Remove unnecessary backslashes
            $reserved = "[nrtvef\\\\\${$reserved}]|[0-7]|x[0-9a-fA-F]|u\{[0-9a-fA-F]+\}";

            if ($token->id === \T_CONSTANT_ENCAPSED_STRING
                    || $token->Next !== $token->String->StringClosedBy
                    || $token->String->id !== \T_START_HEREDOC) {
                $reserved .= '|$';
            }

            $double = Regex::replace(
                "/(?<!\\\\)\\\\\\\\(?!{$reserved})/",
                '\\',
                $double
            );

            // "\\\{$a}" becomes "\\\{", which escapes to "\\\\{", but we need
            // the brace to remain escaped lest it become a T_CURLY_OPEN
            if ($token->id !== \T_CONSTANT_ENCAPSED_STRING
                    && ($token->Next !== $token->String->StringClosedBy)) {
                $double = Regex::replace(
                    '/(?<!\\\\)(\\\\(?:\\\\\\\\)*)\\\\(\{)$/',
                    '$1$2',
                    $double
                );
            }

            $double = $doubleQuote
                . $this->maybeEscapeEscapes($double, $reserved)
                . $suffix
                . $doubleQuote;

            // Use the double-quoted variant if escape sequences remain after
            // unescaping tabs used for indentation
            if ($this->Formatter->Tab === "\t") {
                $double = Regex::replaceCallback(
                    '/^(?:\\\\t)+(?=\S|$)/m',
                    fn(array $matches) =>
                        str_replace('\t', "\t", $matches[0]),
                    $double,
                );
                if ($token->id !== \T_CONSTANT_ENCAPSED_STRING
                        || $utf8Escapes
                        || Regex::match("/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f{$match}]/", $string)
                        || Regex::match('/(?<!\\\\)(?:\\\\\\\\)*\\\\t/', $double)) {
                    $token->setText($double);
                    continue;
                }
            } elseif ($token->id !== \T_CONSTANT_ENCAPSED_STRING
                    || $utf8Escapes
                    || Regex::match("/[\\x00-\\x09\\x0b\\x0c\\x0e-\\x1f{$match}]/", $string)) {
                $token->setText($double);
                continue;
            }

            $single = "'" . $this->maybeEscapeEscapes(Regex::replace(
                "/(?:\\\\(?=\\\\)|(?<=\\\\)\\\\)|\\\\(?='|\$)|'/",
                '\\\\$0',
                $string
            )) . $suffix . "'";

            if (mb_strlen($single) <= mb_strlen($double)) {
                $token->setText($single);
                continue;
            }

            $token->setText($double);
        }
    }

    private function maybeEscapeEscapes(string $string, string $reserved = "['\\\\]"): string
    {
        // '\Name\\' is valid but confusing, so replace '\' with '\\' in strings
        // where every backslash other than the trailing '\\' is singular
        if (
            $string !== ''
            && $string[-1] === '\\'
            && Regex::matchAll('/(?<!\\\\)\\\\\\\\(?!\\\\)/', $string) === 1
            && !Regex::match("/(?<!\\\\)\\\\(?={$reserved})(?!\\\\\$)/", $string)
            && strpos($string, '\\\\\\') === false
        ) {
            return Regex::replace("/(?<!\\\\)\\\\(?!{$reserved})/", '\\\\$0', $string);
        }
        return $string;
    }
}
