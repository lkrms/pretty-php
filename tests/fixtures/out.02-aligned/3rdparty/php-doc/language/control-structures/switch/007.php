<?php
switch ($beer) {
    case 'tuborg';
    case 'carlsberg';
    case 'stella';
    case 'heineken';
        echo 'Good choice';
        break;
    default;
        echo 'Please make a new selection...';
        break;
}
?>