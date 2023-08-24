<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Filter\Concern\FilterTrait;
use Lkrms\PrettyPHP\Filter\Contract\Filter;

/**
 * Use var_export() to normalise string constants for comparison
 *
 * @api
 */
final class StandardiseStrings implements Filter
{
    use FilterTrait;

    public function filterTokens(array $tokens): array
    {
        $string = '';
        foreach ($tokens as $token) {
            if ($token->id === T_CONSTANT_ENCAPSED_STRING) {
                eval("\$string = {$token->text};");
                $token->setText(var_export($string, true));
            }
        }

        return $tokens;
    }
}
