<?php

set_time_limit(0);
ignore_user_abort(TRUE);


require __DIR__ . '/../src/MySQLDump.php';

$time = -microtime(TRUE);

$dump = new MySQLDump(new mysqli('localhost', 'root', 'password', 'database'));
$dump->save('dump ' . date('Y-m-d H-i') . '.sql.gz');

$time += microtime(TRUE);
echo "FINISHED (in $time s)";
