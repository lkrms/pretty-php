<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Command;

use Lkrms\Cli\CliApplication;
use Lkrms\Console\Catalog\ConsoleLevels as Levels;
use Lkrms\Console\Target\MockTarget;
use Lkrms\Facade\Console;
use Lkrms\Facade\File;
use Lkrms\PrettyPHP\Command\FormatPhp;

final class FormatPhpTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider outputProvider
     * @requires PHP >= 8.0
     *
     * @param string[] $args
     */
    public function testOutput(string $expected, string $code, array $args = [], int $expectedExitStatus = 0): void
    {
        $target = new MockTarget();
        Console::registerTarget($target, Levels::ALL_EXCEPT_DEBUG);

        $this->expectOutputString($expected);

        $basePath = File::createTemporaryDirectory();
        $app = new CliApplication($basePath);

        try {
            $src = tempnam($app->getTempPath(), 'src');
            file_put_contents($src, $code);

            $formatPhp = new FormatPhp($app);
            $exitStatus = $formatPhp(...[...$args, '--no-config', '-o', '-', '--', $src]);
            $this->assertSame($expectedExitStatus, $exitStatus, 'exit status');
        } finally {
            $app->unload();

            File::pruneDirectory($basePath);
            rmdir($basePath);

            Console::deregisterTarget($target);
        }
    }

    /**
     * @return array<string,array{0:string,1:string,2?:string[],3?:int}>
     */
    public static function outputProvider(): array
    {
        return [
            'Symfony' => [
                <<<'PHP'
<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Acme;

use Other\Qux;

/**
 * Coding standards demonstration.
 */
class FooBar
{
    public const SOME_CONST = 42;

    private string $fooBar;

    /**
     * @param $dummy some argument description
     */
    public function __construct(
        string $dummy,
        private Qux $qux
    ) {
        $this->fooBar = $this->transformText($dummy);
    }

    /**
     * @deprecated
     */
    public function someDeprecatedMethod(): string
    {
        trigger_deprecation('symfony/package-name', '5.1', 'The %s() method is deprecated, use Acme\Baz::someMethod() instead.', __METHOD__);

        return Baz::someMethod();
    }

    /**
     * Transforms the input given as the first argument.
     *
     * @param $options an options collection to be used within the transformation
     *
     * @throws \RuntimeException when an invalid option is provided
     */
    private function transformText(bool|string $dummy, array $options = []): ?string
    {
        $defaultOptions = [
            'some_default' => 'values',
            'another_default' => 'more values',
        ];

        foreach ($options as $name => $value) {
            if (!array_key_exists($name, $defaultOptions)) {
                throw new \RuntimeException(sprintf('Unrecognized option "%s"', $name));
            }
        }

        $mergedOptions = array_merge($defaultOptions, $options);

        if (true === $dummy) {
            return 'something';
        }

        if (\is_string($dummy)) {
            if ('values' === $mergedOptions['some_default']) {
                return substr($dummy, 0, 5);
            }

            return ucwords($dummy);
        }

        return null;
    }

    /**
     * Performs some basic operations for a given value.
     */
    private function performOperations(mixed $value = null, bool $theSwitch = false): void
    {
        if (!$theSwitch) {
            return;
        }

        $this->qux->doFoo($value);
        $this->qux->doBar($value);
    }
}

PHP,
                <<<'PHP'
<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Acme;
use Other\Qux;
/**
 * Coding standards demonstration.
 */
class FooBar {
    public const SOME_CONST = 42;
    private string $fooBar;
    /**
     * @param $dummy some argument description
     */
    public function __construct(string $dummy, private Qux $qux) {
        $this->fooBar = $this->transformText($dummy);
    }
    /**
     * @deprecated
     */
    public function someDeprecatedMethod(): string {
        trigger_deprecation('symfony/package-name', '5.1', 'The %s() method is deprecated, use Acme\Baz::someMethod() instead.', __METHOD__);
        return Baz::someMethod();
    }
    /**
     * Transforms the input given as the first argument.
     *
     * @param $options an options collection to be used within the transformation
     *
     * @throws \RuntimeException when an invalid option is provided
     */
    private function transformText(
        bool|string $dummy,
        array $options = []
    ): ?string {
        $defaultOptions = [
            'some_default' => 'values',
            'another_default' => 'more values',
        ];

        foreach ($options as $name => $value) {
            if (!array_key_exists($name, $defaultOptions)) {
                throw new \RuntimeException(sprintf('Unrecognized option "%s"', $name));
            }
        }

        $mergedOptions = array_merge($defaultOptions, $options);

        if (true === $dummy) {
            return 'something';
        }

        if (\is_string($dummy)) {
            if ('values' === $mergedOptions['some_default']) {
                return substr($dummy, 0, 5);
            }
            return ucwords($dummy);
        }
        return null;
    }
    /**
     * Performs some basic operations for a given value.
     */
    private function performOperations(
        mixed $value = null,
        bool $theSwitch = false
    ): void {
        if (!$theSwitch) {
            return;
        }

        $this->qux->doFoo($value);
        $this->qux->doBar($value);
    }
}
PHP,
                ['--preset', 'symfony'],
            ],
            'Drupal #1' => [
                <<<'PHP'
<?php

/**
 * @file
 * File description.
 *
 * An extended description of the file.
 */

function foo($bar, $baz, $qux, $quux) {
  if ($bar) {
    return $qux;
  }
  elseif ($baz) {
    return $quux;
  }
  else {
    return null;
  }
}

PHP,
                <<<'PHP'
<?php
/**
 * @file
 * File description.
 *
 * An extended description of the file.
 */
function foo($bar, $baz, $qux, $quux)
{
if ($bar) {
return $qux;
} elseif ($baz) {
return $quux;
} else {
return null;
}
}
PHP,
                ['--preset', 'drupal'],
            ],
            'Drupal #2' => [
                <<<'PHP'
<?php

/**
 * @file
 * File description.
 *
 * An extended description of the file.
 */

namespace Vendor;

use Vendor\Qux\A;
use Vendor\Quux;
use Bar;

/**
 * Class description.
 */
class Foo {

  public const ANSWER = 42;

  private string $question;

  /**
   * @param Bar|Quux|A|null $quux
   */
  public function __construct(?string $question, $quux = null) {
    if ($quux === null) {
      $this->question = $this->sanitise($question);
    }
    elseif ($quux instanceof Quux) {
      $this->question = $quux->escape($question);
    }
    else {
      $this->question = (string) $quux;
    }
  }

  /**
   * Cleans up the question
   *
   * @param array<string,mixed> $options
   *
   * @throws \RuntimeException when an invalid option is provided.
   */
  private function sanitise(?string $question, array $options = []): ?string {
    $defaultOptions = [
      'option1' => 'value',
      'option2' => 'some other value',
    ];

    // This loop is completely pointless, of course
    do {
      foreach ($options as $name => $value) {
        if (!array_key_exists($name, $defaultOptions)) {
          throw new \RuntimeException(sprintf('Invalid option: %s', $name));
        }
      }
    } while (false);

    $mergedOptions = array_merge($defaultOptions, $options);

    if (null === $question) {
      return null;
    }

    if ('value' === $mergedOptions['option1']) {
      return substr($question, 0, 5);
    }

    return ucwords($question);
  }

}

PHP,
                <<<'PHP'
<?php
/**
 * @file
 * File description.
 *
 * An extended description of the file.
 */
namespace Vendor;
use Vendor\Qux\A;
use Vendor\Quux;
use Bar;
/**
 * Class description.
 */
class Foo
{
public const ANSWER = 42;
private string $question;
/**
 * @param Bar|Quux|A|null $quux
 */
public function __construct(?string $question, $quux = null)
{

if ($quux === null) {

$this->question = $this->sanitise($question);

} elseif ($quux instanceof Quux) {

$this->question = $quux->escape($question);

} else {

$this->question = (string) $quux;

}

}
/**
 * Cleans up the question
 *
 * @param array<string,mixed> $options
 *
 * @throws \RuntimeException when an invalid option is provided.
 */
private function sanitise(?string $question, array $options = []): ?string
{
$defaultOptions = ['option1' => 'value',
                   'option2' => 'some other value',];

// This loop is completely pointless, of course
do {
foreach ($options as $name => $value) {
if (!array_key_exists($name, $defaultOptions)) {
throw new \RuntimeException(sprintf('Invalid option: %s', $name));
}
}
} while (false);

$mergedOptions = array_merge($defaultOptions, $options);

if (null === $question) {
return null;
}

if ('value' === $mergedOptions['option1']) {
return substr($question, 0, 5);
}

return ucwords($question);
}
}
PHP,
                ['--preset', 'drupal'],
            ],
        ];
    }
}
