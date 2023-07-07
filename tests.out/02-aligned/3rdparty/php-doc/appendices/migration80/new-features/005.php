<?php
printf('%.*H', (int) ini_get('precision'), $float);
printf('%.*H', (int) ini_get('serialize_precision'), $float);
?>