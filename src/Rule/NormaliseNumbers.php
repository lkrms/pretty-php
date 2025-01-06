<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\TokenIndex;
use Salient\Utility\Exception\ShouldNotHappenException;
use Salient\Utility\Regex;
use Salient\Utility\Str;

/**
 * Normalise integers and floats
 *
 * @api
 */
final class NormaliseNumbers implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 60,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(TokenIndex $idx): array
    {
        return [
            \T_LNUMBER => true,
            \T_DNUMBER => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * Apply the rule to the given tokens
     *
     * Integer literals are normalised by replacing hexadecimal, octal and
     * binary prefixes with `0x`, `0` and `0b` respectively, removing redundant
     * zeroes, and converting hexadecimal digits to uppercase.
     *
     * Float literals are normalised by removing redundant zeroes, adding `0` to
     * empty integer or fractional parts, replacing `E` with `e`, removing `+`
     * from exponents, and expressing them with mantissae between 1.0 and 10.
     *
     * If an underscore is present in the input, underscores are applied to
     * decimal values with no exponent every 3 digits, to hexadecimal values
     * with more than 5 digits every 4 digits, and to binary values every 4
     * digits.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $text = str_replace('_', '', $token->text, $underscores);
            if (
                $token->id === \T_LNUMBER
                // Integer literals that exceed the bounds of `int` are parsed
                // as `T_DNUMBER`, hence these additional checks
                || strpbrk($text, '.Ee') === false
                || strpbrk($text, 'xX') !== false
            ) {
                // Check for octal, binary and hexadecimal literals
                if (!Regex::match(
                    '/^0(?<base>[xob]|(?=[0-7]))0*(?<number>[1-9a-f][0-9a-f]*|0)$/i',
                    $text,
                    $matches,
                )) {
                    if ($underscores) {
                        $length = strlen($text);
                        if ($length > 3) {
                            $text = ltrim($this->split($text, 3, $length, ' '));
                        }
                        $token->setText($text);
                    }
                    continue;
                }

                $base = $matches['base'];
                $number = $matches['number'];
                $length = 0;
                $split = 0;

                switch ($base) {
                    case 'X':
                        $base = 'x';
                        // No break
                    case 'x':
                        $number = strtr($number, 'abcdef', 'ABCDEF');
                        $length = strlen($number);
                        if ($length !== 5 || $underscores) {
                            $split = 4;
                        }
                        break;

                    case 'O':
                    case 'o':
                        // Don't use octal notation introduced in PHP 8.1
                        $base = '';
                        // No break
                    case '':
                        // Replace `00` with `0`
                        if ($number === '0') {
                            $number = '';
                        }
                        break;

                    case 'B':
                        $base = 'b';
                        // No break
                    case 'b':
                        $length = strlen($number);
                        $split = 4;
                        break;
                }

                if ($underscores && $split && $length > $split) {
                    $number = $this->split($number, $split, $length, '0');
                }

                $token->setText("0{$base}{$number}");
                continue;
            }

            if (!Regex::match(
                '/^0*+(?<integer>[0-9]+)?(?:\.(?<fractional>[0-9]+(?<!0))?0*)?(?:e(?<sign>[-+])?0*(?<exponent>[0-9]+))?$/i',
                $text,
                $matches,
                \PREG_UNMATCHED_AS_NULL,
            )) {
                // @codeCoverageIgnoreStart
                throw new ShouldNotHappenException(
                    sprintf('Invalid %s: %s', $token->getTokenName(), $token->text)
                );
                // @codeCoverageIgnoreEnd
            }

            $integer = Str::coalesce($matches['integer'], '0');
            $fractional = Str::coalesce($matches['fractional'], '0');
            $exponent = $matches['exponent'];

            if ($exponent === null) {
                if ($underscores) {
                    $length = strlen($integer);
                    if ($length > 3) {
                        $integer = ltrim($this->split($integer, 3, $length, ' '));
                    }
                    $length = strlen($fractional);
                    if ($length > 3) {
                        $fractional = $this->split($fractional, 3, $length, '');
                    }
                }
                $token->setText("{$integer}.{$fractional}");
                continue;
            }

            $integer = (int) $integer;
            if ($integer === 0 && $fractional === '0') {
                $exponent = 0;
            } else {
                $exponent = (int) $exponent;
                if ($matches['sign'] === '-') {
                    $exponent = -$exponent;
                }
            }

            // Normalise the mantissa to a value >= 1.0 and < 10.0 if possible
            while ($integer > 9) {
                $modulus = $integer % 10;
                $integer = ($integer - $modulus) / 10;
                if ($fractional === '0') {
                    $fractional = (string) $modulus;
                } else {
                    $fractional = "{$modulus}{$fractional}";
                }
                $exponent++;
            }

            while ($integer === 0 && $fractional !== '0') {
                $integer = (int) $fractional[0];
                /** @var string */
                $fractional = substr($fractional, 1);
                if ($fractional === '') {
                    $fractional = '0';
                }
                $exponent--;
            }

            $token->setText("{$integer}.{$fractional}e{$exponent}");
        }
    }

    /**
     * @param int<1,max> $split
     * @param int<0,max> $length
     */
    private function split(string $string, int $split, int $length, string $padWith): string
    {
        if ($padWith !== '') {
            $extra = $length % $split;
            if ($extra) {
                $string = str_repeat($padWith, $split - $extra) . $string;
            }
        }
        /** @var string[] */
        $parts = str_split($string, $split);
        return implode('_', $parts);
    }
}
