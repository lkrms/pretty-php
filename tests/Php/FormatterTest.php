<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use FilesystemIterator as FS;
use Lkrms\Facade\File;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Rule\AddBlankLineBeforeDeclaration;
use Lkrms\Pretty\Php\Rule\AlignAssignments;
use Lkrms\Pretty\Php\Rule\DeclareArgumentsOnOneLine;
use Lkrms\Pretty\Php\Rule\SimplifyStrings;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
        $dir   = new RecursiveDirectoryIterator($inDir, FS::KEY_AS_PATHNAME | FS::CURRENT_AS_FILEINFO | FS::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($dir);
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
                switch (explode(DIRECTORY_SEPARATOR, $relPath)[0]) {
                    case 'phpfmt':
                        $insertSpaces = false;
                        break;
                    case 'php-doc':
                        $skipRules = [
                            SimplifyStrings::class,
                            DeclareArgumentsOnOneLine::class,
                            AddBlankLineBeforeDeclaration::class,
                            AlignAssignments::class,
                        ];
                        break;
                }
                if (!file_exists($outFile)) {
                    printf("Formatting %s\n", $relPath);
                    File::maybeCreateDirectory(dirname($outFile));
                    $formatter = new Formatter($insertSpaces,
                                               $tabSize,
                                               $skipRules,
                                               $addRules);
                    file_put_contents(
                        $outFile,
                        $out = $formatter->format($in, 3, $relPath)
                    );
                } else {
                    $out = file_get_contents($outFile);
                }
                $this->assertFormatterOutputIs($in,
                                               $out,
                                               $skipRules,
                                               $addRules,
                                               $insertSpaces,
                                               $tabSize,
                                               $relPath);
            }
        }
    }
}
