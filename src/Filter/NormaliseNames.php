<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Filter\Concern\FilterTrait;

/**
 * Convert namespaced names to PHP 8.0 name tokens
 *
 * @api
 */
final class NormaliseNames implements Filter
{
    use FilterTrait;

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        if (\PHP_VERSION_ID >= 80000 || !$tokens) {
            return $tokens;
        }

        $class = get_class($tokens[0]);

        $filtered = [];
        $count = count($tokens);
        $lastWasSeparator = false;
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($lastWasSeparator) {
                $lastWasSeparator = false;
            } elseif ($token->id === \T_NS_SEPARATOR) {
                if ($this->Idx->MaybeReserved[$tokens[$i - 1]->id]) {
                    array_pop($filtered);
                    $name = [$tokens[$i - 1], $token];
                    $id = $tokens[$i - 1]->id === \T_NAMESPACE
                        ? \T_NAME_RELATIVE
                        : \T_NAME_QUALIFIED;
                } else {
                    $name = [$token];
                    $id = \T_NAME_FULLY_QUALIFIED;
                }

                $lastWasSeparator = true;
                while (true) {
                    if (!$this->Idx->MaybeReserved[$tokens[$i + 1]->id]) {
                        break;
                    }
                    $name[] = $tokens[++$i];
                    if ($tokens[$i + 1]->id !== \T_NS_SEPARATOR) {
                        $lastWasSeparator = false;
                        break;
                    }
                    $name[] = $tokens[++$i];
                }
                if ($lastWasSeparator) {
                    array_pop($name);
                    $i--;

                    // Leave `use my\{a, b};` alone
                    if (count($name) === 1) {
                        $filtered[] = $name[0];
                        continue;
                    }
                }

                $text = '';
                foreach ($name as $t) {
                    $text .= $t->text;
                }

                $token = new $class($id, $text, $token->line, $token->pos);
            }

            $filtered[] = $token;
        }

        return $filtered;
    }
}
