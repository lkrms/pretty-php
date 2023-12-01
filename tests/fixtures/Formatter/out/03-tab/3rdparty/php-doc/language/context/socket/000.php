<?php
// connect to the internet using the '192.168.0.100' IP
$opts = array(
	'socket' => array(
		'bindto' => '192.168.0.100:0',
	),
);

// connect to the internet using the '192.168.0.100' IP and port '7000'
$opts = array(
	'socket' => array(
		'bindto' => '192.168.0.100:7000',
	),
);

// connect to the internet using the '2001:db8::1' IPv6 address
// and port '7000'
$opts = array(
	'socket' => array(
		'bindto' => '[2001:db8::1]:7000',
	),
);

// connect to the internet using port '7000'
$opts = array(
	'socket' => array(
		'bindto' => '0:7000',
	),
);

// create the context...
$context = stream_context_create($opts);

// ...and use it to fetch the data
echo file_get_contents('http://www.example.com', false, $context);

?>