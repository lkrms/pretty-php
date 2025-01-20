<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Salient\Utility\Regex;

/**
 * Adapted from PhpParser\CodeTestParser in nikic/php-parser
 */
final class PhpParserTestParser
{
    /**
     * @param int<1,max> $chunksPerTest
     * @return array{string,string[]}
     */
    public function parseTest(string $code, int $chunksPerTest): array
    {
        // Evaluate @@{expr}@@ expressions
        $code = Regex::replaceCallback(
            '/@@\{(.*?)\}@@/',
            function ($matches) {
                return eval('return ' . $matches[1] . ';');
            },
            $code
        );

        // Parse sections
        $parts = Regex::split("/\n-----(?:\n|\$)/", $code);

        // First part is the name
        $name = array_shift($parts);

        // Multiple sections are possible
        $chunks = array_chunk($parts, $chunksPerTest);
        $tests = [];
        foreach ($chunks as $chunk) {
            // Extract the first part of each section
            $tests[] = array_shift($chunk);
        }

        return [$name, $tests];
    }
}
