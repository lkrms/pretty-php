<?php

// storing password hash
$query  = sprintf("INSERT INTO users(name,pwd) VALUES('%s','%s');",
                  pg_escape_string($username),
                  password_hash($password, PASSWORD_DEFAULT));
$result = pg_query($connection, $query);

// querying if user submitted the right password
$query = sprintf("SELECT pwd FROM users WHERE name='%s';",
                 pg_escape_string($username));
$row   = pg_fetch_assoc(pg_query($connection, $query));

if ($row && password_verify($password, $row['pwd'])) {
    echo 'Welcome, ' . htmlspecialchars($username) . '!';
} else {
    echo 'Authentication failed for ' . htmlspecialchars($username) . '.';
}

?>