<?php
mb_ereg_replace('\s*(?<word>\w+)\s*', "_\k<word>_\k'word'_", ' foo ');
// => "_foo_foo_"
?>