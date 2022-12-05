<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use FilesystemIterator as FS;
use Lkrms\Pretty\Php\Formatter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FormatterTest extends \PHPUnit\Framework\TestCase
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
        $this->runFormatter($in, $out);
    }

    public function testFormatter()
    {
        [$inDir, $outDir] = [
            dirname(__DIR__) . '.in',
            dirname(__DIR__) . '.out',
        ];
        $dir   = new RecursiveDirectoryIterator($inDir, FS::KEY_AS_PATHNAME | FS::CURRENT_AS_FILEINFO | FS::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($dir);
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'in') {
                $in      = file_get_contents((string)$file);
                $outFile = $outDir . substr((string)$file, strlen($inDir), -3) . '.out';
                if (!file_exists($outFile)) {
                    printf("Formatting %s\n", (string)$file);
                    $out = (new Formatter("\t"))->format($in);
                    file_put_contents($outFile, $out);
                } else {
                    $out = file_get_contents($outFile);
                }
                $this->runFormatter($in, $out, "\t", $file->getBasename());
            }
        }
    }

    private function runFormatter(string $code, string $expected, string $tab = '    ', string $message = ''): void
    {
        $formatter = new Formatter($tab);
        $this->assertSame($expected, $formatter->format($code), $message);
    }
}
