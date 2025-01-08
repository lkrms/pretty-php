<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Bootstrap;

use Lkrms\PrettyPHP\Tests\TestCase;
use Salient\Contract\Core\FileDescriptor;
use Salient\Core\Process;

final class BootstrapTest extends TestCase
{
    /**
     * @dataProvider bootstrapProvider
     */
    public function testBootstrap(string $pattern, string $file): void
    {
        $process = new Process([...self::PHP_COMMAND, __DIR__ . '/' . $file]);
        $this->assertSame(255, $process->run());
        $this->assertMatchesRegularExpression($pattern, $process->getOutput(FileDescriptor::ERR));
    }

    /**
     * @return array<array{string,string}>
     */
    public static function bootstrapProvider(): array
    {
        $values = \PHP_VERSION_ID < 80000
            ? ['T_MATCH', 'T_ATTRIBUTE']
            : (\PHP_VERSION_ID < 80100
                ? ['T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG', 'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG']
                : (\PHP_VERSION_ID < 80400
                    ? ['T_PROTECTED_SET', 'T_PRIVATE_SET']
                    : ['T_CLOSE_ALT', 'T_ATTRIBUTE_COMMENT']));

        return [
            [
                sprintf('/\b%s is invalid\b/', $values[1]),
                'invalid-tokens.php',
            ],
            [
                sprintf('/\b%s and %s have the same ID\b/', ...$values),
                'tokens-with-same-id.php',
            ],
            [
                sprintf('/\b%s is invalid\b/', 'T_LOGICAL_NOT'),
                'invalid-single-character-tokens.php',
            ],
        ];
    }
}
