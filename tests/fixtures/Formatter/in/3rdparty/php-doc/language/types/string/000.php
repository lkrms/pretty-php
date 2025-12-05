<?php
echo 'this is a simple string', PHP_EOL;

echo 'You can also have embedded newlines in
strings this way as it is
okay to do', PHP_EOL;

// Outputs: Arnold once said: "I'll be back"
echo 'Arnold once said: "I\'ll be back"', PHP_EOL;

// Outputs: You deleted C:\*.*?
echo 'You deleted C:\\*.*?', PHP_EOL;

// Outputs: You deleted C:\*.*?
echo 'You deleted C:\*.*?', PHP_EOL;

// Outputs: This will not expand: \n a newline
echo 'This will not expand: \n a newline', PHP_EOL;

// Outputs: Variables do not $expand $either
echo 'Variables do not $expand $either', PHP_EOL;
?>