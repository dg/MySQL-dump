<?php

set_time_limit(0);
ignore_user_abort(TRUE);


require __DIR__ . '/../src/MySQLDump.php';

$time = -microtime(TRUE);

$conditions = '`enabled` = 1';//Dump only data with `enabled` field equal to 1. Will be applied for all tables
//OR:
$conditions = array(
	'users' => '`registered` = 1 AND `enabled` = 1',//Dump registered and enabled users only
	'cities' => '`population` > 1000',//Dump cities with population greater than thousand
	'news,articles' => '`header` LIKE "Update%"',//Dump news articles only with headers beginning from 'Update'
	'`created` > "2014-01-01"',//Will be applied for all tables (No key with table name specified). This condition will be appended for all tables conditions (Even for defined above)
);

$dump = new MySQLDump(new mysqli('localhost', 'root', 'password', 'database'), $conditions);
$dump->save('dump ' . date('Y-m-d H-i') . '.sql.gz');

$time += microtime(TRUE);
echo "FINISHED (in $time s)";
