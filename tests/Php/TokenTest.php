<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Lkrms\Pretty\Php\Formatter;

final class TokenTest extends \PHPUnit\Framework\TestCase
{
    public function testRenderPhpDoc()
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
        $this->runFormatter($in, $out);
    }

    private function runFormatter(string $code, string $expected)
    {
        $formatter = new Formatter();
        $this->assertSame($expected, $formatter->format($code));

        //$this->assertSame($expected, $output = $formatter->format($code));
        //file_put_contents("/tmp/formatter.out", $output);
    }
}
