<?php

/*
 * On /path/to/user.ini contains the following settings:
 *
 * listen = localhost:${DRUPAL_FPM_PORT:-9000}
 */

$user_ini = parse_ini_file('/path/to/user.ini');
echo $user_ini['listen'];  // localhost:9000
