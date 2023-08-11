<?php
$url = 'http://www.example.com/redirecting_page.php';

$fp = fopen($url, 'r');

$meta_data = stream_get_meta_data($fp);
foreach ($meta_data['wrapper_data'] as $response) {

    /* Were we redirected? */
    if (strtolower(substr($response, 0, 10)) == 'location: ') {

        /* update $url with where we were redirected to */
        $url = substr($response, 10);
    }

}

?>