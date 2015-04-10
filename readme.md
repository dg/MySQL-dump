MySQL Dump Utility
==================

This is a backup utility used to dump a database for backup or transfer to another MySQL server.
The dump typically contains SQL statements to create the table, populate it, or both.

It requires PHP 5.0.5 or later.

Usage
-----

Create [MySQLi](http://www.php.net/manual/en/mysqli.construct.php) object and pass it to the MySQLDump:

	$dump = new MySQLDump(new mysqli('localhost', 'root', 'password', 'database'));

You can optionally specify how each table or view should be exported:

	$dump->tables['search_cache'] = MySQLDump::DROP | MySQLDump::CREATE;
	$dump->tables['log'] = MySQLDump::NONE;

Then simply call `save()` or `write()`:

	$dump->save('export.sql.gz');

If you want to set a connection charset different from 'utf8', simply pass it as optional parameter:

	$dump = new MySQLDump(new mysqli('localhost', 'root', 'password', 'database'), 'latin1');

-----
Project at GitHub: http://github.com/dg/MySQL-dump

(c) David Grudl, 2008, 2013 (http://davidgrudl.com)
