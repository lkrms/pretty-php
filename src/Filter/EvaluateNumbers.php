<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;

/**
 * Evaluate numbers for comparison
 *
 * @api
 */
final class EvaluateNumbers implements Filter
{
    use ExtensionTrait;

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        $number = 0;
        foreach ($tokens as $token) {
            if ($this->TypeIndex->Number[$token->id]) {
                eval("\$number = {$token->text};");
                $token->text = var_export($number, true);
            }
        }

        return $tokens;
    }
}
