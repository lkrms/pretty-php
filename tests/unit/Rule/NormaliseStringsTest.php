<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Formatter;

final class NormaliseStringsTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider outputProvider
     *
     * @param array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null} $options
     */
    public function testOutput(string $expected, string $code, array $options = []): void
    {
        $this->assertFormatterOutputIs($expected, $code, $this->getFormatter($options));
    }

    /**
     * @return array<array{string,string,array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null}}>
     */
    public static function outputProvider(): array
    {
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
                [
                    'insertSpaces' => false,
                ],
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
                [
                    'insertSpaces' => false,
                ],
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
            ]
        ];
    }
}
