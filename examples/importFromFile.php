<?php

set_time_limit(0);
ignore_user_abort(TRUE);


require __DIR__ . '/../src/MySQLImport.php';

$time = -microtime(TRUE);

$import = new MySQLImport(new mysqli('localhost', 'root', 'password', 'database'));
$import->load('dump.sql.gz');

$time += microtime(TRUE);
echo "FINISHED (in $time s)";
