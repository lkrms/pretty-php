<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Exception\RuleException;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\Utility\Pcre;
use Lkrms\Utility\Str;

/**
 * Normalise integers and floats
 */
final class NormaliseNumbers implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 60;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return [
            \T_LNUMBER,
            \T_DNUMBER,
        ];
    }

    public static function getRequiresSortedTokens(): bool
    {
        return false;
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $text = str_replace('_', '', $token->text, $underscores);
            if (
                $token->id === \T_LNUMBER ||
                strpbrk($text, '.Ee') === false ||
                strpbrk($text, 'xX') !== false
            ) {
                if (!Pcre::match(
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
                $pad = 0;
                $split = 0;

                switch ($base) {
                    case 'X':
                        $base = 'x';
                        // No break
                    case 'x':
                        $number = strtr($number, 'abcdef', 'ABCDEF');
                        $length = strlen($number);
                        if ($length !== 5 || $underscores) {
                            $pad = $length % 2;
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
                        $pad = $length % 2;
                        $split = 4;
                        break;
                }

                if ($underscores && $split && $length > $split) {
                    $number = $this->split($number, $split, $length, '0');
                } elseif ($pad) {
                    $number = str_repeat('0', $pad) . $number;
                }

                $token->setText("0{$base}{$number}");
                continue;
            }

            if (!Pcre::match(
                '/^0*+(?<integer>[0-9]+)?(?:\.(?<fractional>[0-9]+(?<!0))?0*)?(?:e(?<sign>[-+])?0*(?<exponent>[0-9]+))?$/i',
                $text,
                $matches,
                PREG_UNMATCHED_AS_NULL,
            )) {
                throw new RuleException(
                    sprintf('Invalid %s: %s', $token->getTokenName(), $token->text)
                );
            }

            $integer = Str::coalesce($matches['integer'], '0');
            $fractional = Str::coalesce($matches['fractional'], '0');
            $exponent = $matches['exponent'];

            if ($exponent === null) {
                $token->setText("{$integer}.{$fractional}");
                continue;
            }

            $integer = (int) $integer;
            $exponent = (int) $exponent;
            if ($matches['sign'] === '-') {
                $exponent = -$exponent;
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
                $fractional = substr($fractional, 1);
                if ($fractional === '') {
                    $fractional = '0';
                }
                $exponent--;
            }

            $token->setText("{$integer}.{$fractional}e{$exponent}");
        }
    }

    private function split(string $string, int $split, int $length, string $padWith): string
    {
        $extra = $length % $split;
        if ($extra) {
            $string = str_repeat($padWith, $split - $extra) . $string;
        }
        return implode('_', str_split($string, $split));
    }
}
