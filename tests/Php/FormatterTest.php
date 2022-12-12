<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use FilesystemIterator as FS;
use Lkrms\Pretty\Php\Formatter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FormatterTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    public function testRenderComment()
    {
        $in  = <<<PHP
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
        if (!is_dir($inDir) || !is_dir($outDir)) {
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
                $tab     = basename($file->getPath()) === 'phpfmt' ? "\t" : '    ';
                if (!file_exists($outFile)) {
                    printf("Formatting %s\n", (string) $file);
                    $out = (new Formatter($tab))->format($in);
                    file_put_contents($outFile, $out);
                } else {
                    $out = file_get_contents($outFile);
                }
                $this->assertFormatterOutputIs($in, $out, $tab, $file->getBasename());
            }
        }
    }
}
