<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

final class PlaceCommentsTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider alignCommentProvider
     */
    public function testAlignComment(string $code, string $expected)
    {
        $this->assertFormatterOutputIs($code, $expected);
    }

    public static function alignCommentProvider()
    {
        return [
            'switch comments' => [
                <<<'PHP'
                <?php

                switch ($a) {
                //
                case 0:
                case 1:
                //
                func();
                // Aligns with previous statement
                case 2:
                //
                case 3:
                func2();
                break;

                // Aligns with previous statement

                case 4:
                func();
                break;

                //
                default:
                break;
                }
                PHP,
                <<<'PHP'
                <?php

                switch ($a) {
                    //
                    case 0:
                    case 1:
                        //
                        func();
                        // Aligns with previous statement
                    case 2:
                    //
                    case 3:
                        func2();
                        break;

                        // Aligns with previous statement

                    case 4:
                        func();
                        break;

                    //
                    default:
                        break;
                }

                PHP,
            ]
        ];
    }
}
