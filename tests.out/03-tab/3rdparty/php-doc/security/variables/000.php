<?php
// remove a file from the user's home directory... or maybe
// somebody else's?
unlink($evil_var);

// Write logging of their access... or maybe an /etc/passwd entry?
fwrite($fp, $evil_var);

// Execute something trivial.. or rm -rf *?
system($evil_var);
exec($evil_var);

?>