<?php

class MyRecDirIt extends RecursiveDirectoryIterator
{
    function current()
    {
        return rawurldecode($this->getSubPathName())
            . (is_dir(parent::current()) ? " [DIR]" : "");
    }
}

$f = "rar://" . rawurlencode(dirname(__FILE__))
    . DIRECTORY_SEPARATOR . 'dirs_and_extra_headers.rar#';

$it = new RecursiveTreeIterator(new MyRecDirIt($f));

foreach ($it as $s) {
    echo $s, "\n";
}
?>