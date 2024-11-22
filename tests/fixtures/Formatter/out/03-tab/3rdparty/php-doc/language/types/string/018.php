<?php
$juices = array('apple', 'orange', 'string_key' => 'purple');

echo "He drank some $juices[0] juice.";
echo PHP_EOL;
echo "He drank some $juices[1] juice.";
echo PHP_EOL;
echo "He drank some $juices[string_key] juice.";
echo PHP_EOL;

class A
{
	public $s = 'string';
}

$o = new A();

echo "Object value: $o->s.";
?>