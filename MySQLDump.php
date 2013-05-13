<?php

/**
 * MySQL database dump.
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2008 David Grudl
 * @license    New BSD License
 * @link       http://phpfashion.com/
 * @version    0.9
 */
class MySQLDump
{
	const MAX_SQL_SIZE = 1e6;

	/** @var mysqli */
	private $connection;



	/**
	 * Connects to database.
	 * @param  mysqli connection
	*/
	public function __construct(mysqli $connection)
	{
		$this->connection = $connection;

		if ($connection->connect_errno) {
			throw new Exception($connection->connect_error);

		} elseif (!$connection->set_charset('utf8')) { // was added in MySQL 5.0.7 and PHP 5.0.5, fixed in PHP 5.1.5)
			throw new Exception($connection->error);
		}
	}



	/**
	 * Sends dump to browser.
	 * @param  string filename
	 * @return void
	*/
	public function send($file)
	{
		ini_set('zlib.output_compression', true);
		header('Content-Type: ' . (strcasecmp(substr($file, -3), '.gz') ? 'text/plain' : 'application/x-gzip'));
		header('Content-Disposition: attachment; filename="' . $file . '"');
		header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');
		header('Connection: close');
		$this->write(fopen('php://output', 'wb'));
	}



	/**
	 * Saves dump to the file.
	 * @param  string filename
	 * @return void
	*/
	public function save($file)
	{
		$handle = strcasecmp(substr($file, -3), '.gz') ? fopen($file, 'wb') : gzopen($file, 'wb');
		if (!$handle) {
			throw new Exception("ERROR: Cannot write file '$file'.");
		}
		$this->write($handle);
	}



	/**
	 * Writes dump to logical file.
	 * @param  resource
	 * @return void
	*/
	public function write($handle = NULL)
	{
		if ($handle === NULL) {
			$handle = fopen('php://output', 'wb');
		} elseif (!is_resource($handle) || get_resource_type($handle) !== 'stream') {
			throw new Exception('Argument must be stream resource.');
		}

		$tables = array();

		$res = $this->connection->query('SHOW TABLES');
		while ($row = $res->fetch_row()) {
			$tables[] = $row[0];
		}
		$res->close();

		$db = $this->connection->query('SELECT DATABASE()')->fetch_row();
		fwrite($handle, "-- Created at " . date('j.n.Y G:i') . " using David Grudl MySQL Dump Utility\n"
			. (isset($_SERVER['HTTP_HOST']) ? "-- Host: $_SERVER[HTTP_HOST]\n" : '')
			. "-- MySQL Server: " . $this->connection->server_info . "\n"
			. "-- Database: " . $db[0] . "\n"
			. "\n"
			. "SET NAMES utf8;\n"
			. "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n"
			. "SET FOREIGN_KEY_CHECKS=0;\n"
		);

		foreach ($tables as $table) {
			$this->dumpTable($handle, $table);
		}

		fwrite($handle, "-- THE END\n");
	}



	/**
	 * Dumps table to logical file.
	 * @param  resource
	 * @return void
	*/
	public function dumpTable($handle, $table)
	{
		$res = $this->connection->query("SHOW CREATE TABLE `$table`");
		$row = $res->fetch_assoc();
		$res->close();

		fwrite($handle, "-- --------------------------------------------------------\n\n");

		$view = isset($row['Create View']);
		fwrite($handle, 'DROP ' . ($view ? 'VIEW' : 'TABLE') . " IF EXISTS `$table`;\n\n");
		fwrite($handle, $row[$view ? 'Create View' : 'Create Table'] . ";\n\n");
		if ($view) {
			return;
		}

		$numeric = array();
		$res = $this->connection->query("SHOW COLUMNS FROM `$table`");
		$cols = array();
		while ($row = $res->fetch_assoc()) {
			$col = $row['Field'];
			$cols[] = '`' . str_replace('`', '``', $col) . '`';
			$numeric[$col] = (bool) preg_match('#^[^(]*(BYTE|COUNTER|SERIAL|INT|LONG|CURRENCY|REAL|MONEY|FLOAT|DOUBLE|DECIMAL|NUMERIC|NUMBER)#i', $row['Type']);
		}
		$cols = '(' . implode(', ', $cols) . ')';
		$res->close();


		$size = 0;
		$res = $this->connection->query("SELECT * FROM `$table`", MYSQLI_USE_RESULT);
		while ($row = $res->fetch_assoc()) {
			$s = '(';
			foreach ($row as $key => $value) {
				if ($value === NULL) {
					$s .= "NULL,\t";
				} elseif ($numeric[$key]) {
					$s .= $value . ",\t";
				} else {
					$s .= "'" . $this->connection->real_escape_string($value) . "',\t";
				}
			}

			if ($size == 0) {
				$s = "INSERT INTO `$table` $cols VALUES\n$s";
			} else {
				$s = ",\n$s";
			}

			$len = strlen($s) - 1;
			$s[$len - 1] = ')';
			fwrite($handle, $s, $len);

			$size += $len;
			if ($size > self::MAX_SQL_SIZE) {
				fwrite($handle, ";\n");
				$size = 0;
			}
		}

		$res->close();
		if ($size) {
			fwrite($handle, ";\n");
		}
		fwrite($handle, "\n\n");
	}

}
