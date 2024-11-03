<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\Support\WordPressTokenTypeIndex;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Tests\Support\TokenTypeIndexTest;

final class WordPressTokenTypeIndexTest extends TokenTypeIndexTest
{
    public static function preserveNewlineProvider(): array
    {
        return [];
    }

    protected static function getIndex(): TokenTypeIndex
    {
        return new WordPressTokenTypeIndex();
    }
}
