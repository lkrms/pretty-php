<?php
// iterate using the procedural API
assert(is_resource($zip));
while ($entry = zip_read($zip)) {
	echo zip_entry_name($entry);
}

// iterate using the object-oriented API
assert($zip instanceof ZipArchive);
for ($i = 0; $entry = $zip->statIndex($i); $i++) {
	echo $entry['name'];
}
?>