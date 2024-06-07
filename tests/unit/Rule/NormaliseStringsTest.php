<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;

final class NormaliseStringsTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider outputProvider
     *
     * @param Formatter|FormatterB $formatter
     */
    public function testOutput(string $expected, string $code, $formatter): void
    {
        $this->assertFormatterOutputIs($expected, $code, $formatter);
    }

    /**
     * @return array<array{string,string,Formatter|FormatterB}>
     */
    public static function outputProvider(): array
    {
        $formatterB = Formatter::build();
        $formatter = $formatterB->build();

        return [
            'leading tabs + tab indentation' => [
                <<<PHP
<?php
if (true) {
\t\$foo = 'bar
\t\tbaz
\t\tqux';
}

PHP,
                <<<'PHP'
<?php
if (true) {
$foo = "bar
\t\tbaz
\t\tqux";
}
PHP,
                $formatterB
                    ->insertSpaces(false),
            ],
            'leading + inline tabs + tab indentation' => [
                <<<PHP
<?php
if (true) {
\t\$foo = "bar
\t\tbaz
\t\tqux\\tquux";
}

PHP,
                <<<'PHP'
<?php
if (true) {
$foo = "bar
\t\tbaz
\t\tqux\tquux";
}
PHP,
                $formatterB
                    ->insertSpaces(false),
            ],
            'leading tabs' => [
                <<<'PHP'
<?php
if (true) {
    $foo = "bar
\t\tbaz
\t\tqux";
}

PHP,
                <<<'PHP'
<?php
if (true) {
$foo = "bar
\t\tbaz
\t\tqux";
}
PHP,
                $formatter,
            ],
            'leading + inline tabs' => [
                <<<'PHP'
<?php
if (true) {
    $foo = "bar
\t\tbaz
\t\tqux\tquux";
}

PHP,
                <<<'PHP'
<?php
if (true) {
$foo = "bar
\t\tbaz
\t\tqux\tquux";
}
PHP,
                $formatter,
            ],
            'NUL' => [
                <<<'PHP'
<?php
"[\0+\x000]";
"[\0+\x001]";
"[\0+\x002]";
"[\0+\x003]";
"[\0+\x004]";
"[\0+\x005]";
"[\0+\x006]";
"[\0+\x007]";
"[\0+\08]";

PHP,
                <<<'PHP'
<?php
"[\000+\0000]";
"[\000+\0001]";
"[\000+\0002]";
"[\000+\0003]";
"[\000+\0004]";
"[\000+\0005]";
"[\000+\0006]";
"[\000+\0007]";
"[\000+\0008]";
PHP,
                $formatter,
            ],
            'escapes' => [
                <<<'PHP'
<?php
'!"#$%&\'()*+,-./:;<=>?@[]^_`{|}~\\';
'!"#$%&\'()*+,-./:;<=>?@[]^_`{|}~\\';
`echo '!"#\$%&'\''()*+,-./:;<=>?@[]^_\`{|}~\' \\`;
<<<'EOF'
!"#$%&'()*+,-./:;<=>?@[]^_`{|}~\
EOF;
<<<EOF
!"#\$%&'()*+,-./:;<=>?@[]^_`{|}~\
EOF;
'\!\"\#\$\%\&\\\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\`\{\|\}\~\\\\';
'\!\"\#\$\%\&\\\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\`\{\|\}\~\\\\';
`echo '\!\"\#\\\$\%\&\'\''\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\\\`\{\|\}\~\\\\'`;
<<<'EOF'
\!\"\#\$\%\&\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\`\{\|\}\~\\
EOF;
<<<EOF
\!\"\#\\\$\%\&\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\`\{\|\}\~\\\\
EOF;
"{$a}!\"#\$%&'()*+,-./:;<=>?@[]^_`{|}~\\";
`{$a}echo '!"#\$%&'\''()*+,-./:;<=>?@[]^_\`{|}~\' \\`;
<<<EOF
{$a}!"#\$%&'()*+,-./:;<=>?@[]^_`{|}~\
EOF;
"{$a}\!\\\"\#\\\$\%\&\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\`\{\|\}\~\\\\";
`{$a}echo '\!\"\#\\\$\%\&\'\''\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\\\`\{\|\}\~\\\\'`;
<<<EOF
{$a}\!\"\#\\\$\%\&\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\`\{\|\}\~\\\\
EOF;

PHP,
                <<<'PHP'
<?php
'!"#$%&\'()*+,-./:;<=>?@[]^_`{|}~\\';
"!\"#$%&'()*+,-./:;<=>?@[]^_`{|}~\\";
`echo '!"#$%&'\''()*+,-./:;<=>?@[]^_\`{|}~\\' \\`;
<<<'EOF'
!"#$%&'()*+,-./:;<=>?@[]^_`{|}~\
EOF;
<<<EOF
!"#$%&'()*+,-./:;<=>?@[]^_`{|}~\
EOF;
'\!\"\#\$\%\&\\\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\`\{\|\}\~\\\\';
"\!\\\"\#\\\$\%\&\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\`\{\|\}\~\\\\";
`echo '\!\"\#\\$\%\&\'\''\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\\\`\{\|\}\~\\\\'`;
<<<'EOF'
\!\"\#\$\%\&\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\`\{\|\}\~\\
EOF;
<<<EOF
\!\"\#\\$\%\&\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\`\{\|\}\~\\\\
EOF;
"{$a}!\"#$%&'()*+,-./:;<=>?@[]^_`{|}~\\";
`{$a}echo '!"#$%&'\''()*+,-./:;<=>?@[]^_\`{|}~\\' \\`;
<<<EOF
{$a}!"#$%&'()*+,-./:;<=>?@[]^_`{|}~\
EOF;
"{$a}\!\\\"\#\\\$\%\&\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\`\{\|\}\~\\\\";
`{$a}echo '\!\"\#\\$\%\&\'\''\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\\\`\{\|\}\~\\\\'`;
<<<EOF
{$a}\!\"\#\\$\%\&\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\`\{\|\}\~\\\\
EOF;
PHP,
                $formatter,
            ],
            'special escapes' => [
                <<<'PHP'
<?php
"\0\e\a\b\e\f\n\r\t\v\x7f\l\kδ";
'\0\33\a\b\e\f\n\r\t\v\177\l\k\u{03b4}';
"\\0\\33\a\b\\e\\f\\n\\r\\t\\v\\177\l\k\\u{03b4}{$a}";
<<<EOF
\0\e\a\b\e\f\n\r\t\v\x7f\l\kδ
EOF;
<<<EOF
\\0\\33\a\b\\e\\f\\n\\r\\t\\v\\177\l\k\\u{03b4}
EOF;

PHP,
                <<<'PHP'
<?php
"\0\33\a\b\e\f\n\r\t\v\177\l\k\u{03b4}";
"\\0\\33\\a\\b\\e\\f\\n\\r\\t\\v\\177\\l\\k\\u{03b4}";
"\\0\\33\\a\\b\\e\\f\\n\\r\\t\\v\\177\\l\\k\\u{03b4}{$a}";
<<<EOF
\0\33\a\b\e\f\n\r\t\v\177\l\k\u{03b4}
EOF;
<<<EOF
\\0\\33\\a\\b\\e\\f\\n\\r\\t\\v\\177\\l\\k\\u{03b4}
EOF;
PHP,
                $formatter,
            ],
            'backticks' => [
                <<<'PHP'
<?php
`echo 'one quote: "'`;
`echo 'two quotes: ""'`;
`echo 'one escaped quote: \"'`;
`echo 'two escaped quotes: \"\"'`;
`echo 'one escaped backtick: \`'`;
`echo 'two escaped backticks: \`\`'`;
`echo 'one double-escaped backtick: \\\`'`;
`echo 'two double-escaped backticks: \\\`\\\`'`;

PHP,
                <<<'PHP'
<?php
`echo 'one quote: "'`;
`echo 'two quotes: ""'`;
`echo 'one escaped quote: \"'`;
`echo 'two escaped quotes: \"\"'`;
`echo 'one escaped backtick: \`'`;
`echo 'two escaped backticks: \`\`'`;
`echo 'one double-escaped backtick: \\\`'`;
`echo 'two double-escaped backticks: \\\`\\\`'`;
PHP,
                $formatter,
            ],
            'curly escapes' => [
                <<<'PHP'
<?php
`\\${a}\\{$a}\\$a\\\${a}\\\{$a}\\\$a`;
"\\${a}\\{$a}\\$a\\\${a}\\\{$a}\\\$a";
<<<EOF
\\${a}\\{$a}\\$a\\\${a}\\\{$a}\\\$a
EOF;
`\\${a}\\{$a}\\$a\\\${a}\\\{$a}\\\$a\\`;
"\\${a}\\{$a}\\$a\\\${a}\\\{$a}\\\$a\\";
<<<EOF
\\${a}\\{$a}\\$a\\\${a}\\\{$a}\\\$a\
EOF;

PHP,
                <<<'PHP'
<?php
`\\${a}\\{$a}\\$a\\\${a}\\\{$a}\\\$a`;
"\\${a}\\{$a}\\$a\\\${a}\\\{$a}\\\$a";
<<<EOF
\\${a}\\{$a}\\$a\\\${a}\\\{$a}\\\$a
EOF;
`\\${a}\\{$a}\\$a\\\${a}\\\{$a}\\\$a\\`;
"\\${a}\\{$a}\\$a\\\${a}\\\{$a}\\\$a\\";
<<<EOF
\\${a}\\{$a}\\$a\\\${a}\\\{$a}\\\$a\\
EOF;
PHP,
                $formatter,
            ],
            'maybeEscapeEscapes' => [
                <<<'PHP'
<?php
'Lkrms\\Tests\\Utility\\Debugging\\';
'\\\\nas01\staff\\';
<<<EOF
foo \
bar \
EOF;
<<<EOF
foo \
bar \ baz
EOF;

PHP,
                <<<'PHP'
<?php
"Lkrms\\Tests\\Utility\\Debugging\\";
"\\\\nas01\\staff\\";
<<<EOF
foo \\
bar \\
EOF;
<<<EOF
foo \\
bar \\ baz
EOF;
PHP,
                $formatter,
            ],
        ];
    }
}
