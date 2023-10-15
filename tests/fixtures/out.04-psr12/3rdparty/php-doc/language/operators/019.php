<?php
// Raises a warning that $name is undefined.
print 'Mr. ' . $name ?? 'Anonymous';

// Prints "Mr. Anonymous"
print 'Mr. ' . ($name ?? 'Anonymous');
?>