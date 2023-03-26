<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Lkrms\Facade\File;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Rule\AddBlankLineBeforeReturn;
use Lkrms\Pretty\Php\Rule\AlignComments;
use Lkrms\Pretty\Php\Rule\AlignLists;
use Lkrms\Pretty\Php\Rule\AlignTernaryOperators;
use Lkrms\Pretty\Php\Rule\ApplyMagicComma;
use Lkrms\Pretty\Php\Rule\Extra\DeclareArgumentsOnOneLine;
use Lkrms\Pretty\Php\Rule\NoMixedLists;
use Lkrms\Pretty\Php\Rule\PreserveOneLineStatements;
use Lkrms\Pretty\Php\Rule\SimplifyStrings;
use Lkrms\Pretty\Php\Rule\SpaceDeclarations;
use SplFileInfo;

final class FormatterTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    public function testEmptyString()
    {
        $in  = '';
        $out = '';
        $this->assertFormatterOutputIs($in, $out);
    }

    public function testRenderComment()
    {
        $in = <<<PHP
            <?php

            /**
            * leading asterisk and space
            *leading asterisk
            *	leading asterisk and tab
            * 	leading asterisk, space and tab
            * 
            *
            no leading asterisk
            	leading tab and no leading asterisk

              */
            PHP;
        $out = <<<PHP
            <?php

            /**
             * leading asterisk and space
             * leading asterisk
             * 	leading asterisk and tab
             * 	leading asterisk, space and tab
             *
             *
             * no leading asterisk
             * leading tab and no leading asterisk
             *
             */

            PHP;
        $this->assertFormatterOutputIs($in, $out);
    }

    public function testFormatter()
    {
        [$inDir, $outDir] = [
            dirname(__DIR__) . '.in',
            dirname(__DIR__) . '.out',
        ];
        if (!is_dir($inDir)) {
            $this->expectNotToPerformAssertions();

            return;
        }
        $files = File::find($inDir);
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'in', ''], true)) {
                $in      = file_get_contents((string) $file);
                $outFile = preg_replace('/\.in$/', '.out', $outDir . substr((string) $file, strlen($inDir)));
                $relPath = substr((string) $file, strlen($inDir) + 1);

                $insertSpaces = true;
                $tabSize      = 4;
                $skipRules    = [];
                $addRules     = [];
                $skipFilters  = [];
                switch (explode(DIRECTORY_SEPARATOR, $relPath)[0]) {
                    case 'phpfmt':
                        $addRules = [
                            AlignComments::class,
                            PreserveOneLineStatements::class,
                        ];
                        $skipRules = [
                            ApplyMagicComma::class,
                        ];
                        $insertSpaces = false;
                        break;
                    case 'php-doc':
                        $addRules = [
                            AlignComments::class,
                            PreserveOneLineStatements::class,
                        ];
                        $skipRules = [
                            SimplifyStrings::class,
                            SpaceDeclarations::class,
                        ];
                        break;
                }
                if (!file_exists($outFile)) {
                    printf("Formatting %s\n", $relPath);
                    File::maybeCreateDirectory(dirname($outFile));
                    $formatter = new Formatter($insertSpaces,
                                               $tabSize,
                                               $skipRules,
                                               $addRules,
                                               $skipFilters);
                    file_put_contents(
                        $outFile,
                        $out = $formatter->format($in, 3, $relPath)
                    );
                } else {
                    $out = file_get_contents($outFile);
                }
                $this->assertFormatterOutputIs($in,
                                               $out,
                                               $addRules,
                                               $skipRules,
                                               $skipFilters,
                                               $insertSpaces,
                                               $tabSize,
                                               $relPath);
            }
        }
    }
}
