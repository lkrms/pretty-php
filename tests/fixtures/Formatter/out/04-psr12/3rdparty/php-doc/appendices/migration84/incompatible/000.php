<?php

$xmlString = '<root><a><b>1</b><b>2</b><b>3</b></a></root>';
$xml = simplexml_load_string($xmlString);

$nodes = $xml->a->b;
foreach ($nodes as $nodeData) {
    echo 'nodeData: ' . $nodeData . "\n";

    $xml = $nodes->asXml();
}
