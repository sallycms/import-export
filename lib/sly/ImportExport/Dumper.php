<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\ImportExport;

use sly_DB_PDO_Persistence;
use sly_DB_Dump;

class Dumper {
	protected $db;
	protected $ignores;
	protected $chunkSize;
	protected $newline;

	public function __construct(sly_DB_PDO_Persistence $db, array $ignoredPrefixes = array()) {
		$this->db        = $db;
		$this->ignores   = $ignoredPrefixes;
		$this->chunkSize = 500;
		$this->newline   = "\n";
	}

	public function setChunkSize($size) {
		$this->chunkSize = $size <= 0 ? 1 : (int) $size;
		return $this;
	}

	public function setNewlineChar($char) {
		$this->newline = $char;
		return $this;
	}

	public function export($filename, $diffFriendly = false, $includeUsers = false) {
		$fp = @fopen($filename, 'wb');

		if (!$fp) {
			throw new Exception('Could not open "'.$filename.'" for writing.');
		}

		$this->writeHeader($fp);
		$this->writeSetupSQL($fp);

		$prefix = $this->db->getPrefix();
		$tables = $this->getTables($includeUsers);

		foreach ($tables as $table) {
			$this->exportCreateStatement($fp, $table);
			$this->exportTableData($fp, $table, $diffFriendly, $prefix);
		}

		$this->writeTeardownSQL($fp);

		fclose($fp);
	}

	protected function getTables($includeUsers) {
		$prefix = $this->db->getPrefix();
		$tables = $this->db->listTables();

		foreach ($tables as $idx => $table) {
			if (!$this->includeTable($table, $includeUsers)) {
				unset($tables[$idx]);
			}
		}

		return $tables;
	}

	protected function includeTable($table, $includeUsers) {
		$prefix = $this->db->getPrefix();

		// no 'sly_' prefix
		if (strstr($table, $prefix) !== $table) {
			return false;
		}

		// do not export the user table
		if (!$includeUsers && $table === $prefix.'user') {
			return false;
		}

		foreach ($this->ignores as $p) {
			$fullPrefix = $prefix.$p;

			if (strpos($table, $fullPrefix) === 0) {
				return false;
			}
		}

		return true;
	}

	protected function writeHeader($fp) {
		sly_DB_Dump::writeHeader($fp);
	}

	protected function writeSetupSQL($fp) {
		$nl = $this->newline;

		// basically everything mysqldump 10.13 does too

		fwrite($fp, $nl);
		fwrite($fp, 'SET @OLD_CHARACTER_SET_CLIENT = @@CHARACTER_SET_CLIENT;'.$nl);
		fwrite($fp, 'SET @OLD_CHARACTER_SET_RESULTS = @@CHARACTER_SET_RESULTS;'.$nl);
		fwrite($fp, 'SET @OLD_COLLATION_CONNECTION = @@COLLATION_CONNECTION;'.$nl);
		fwrite($fp, 'SET NAMES utf8;'.$nl);
		fwrite($fp, 'SET @OLD_TIME_ZONE = @@TIME_ZONE;'.$nl);
		fwrite($fp, 'SET TIME_ZONE = \'+00:00\';'.$nl);
		fwrite($fp, 'SET @OLD_UNIQUE_CHECKS = @@UNIQUE_CHECKS, UNIQUE_CHECKS = 0;'.$nl);
		fwrite($fp, 'SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS = 0;'.$nl);
		fwrite($fp, 'SET @OLD_SQL_MODE = @@SQL_MODE, SQL_MODE = \'NO_AUTO_VALUE_ON_ZERO\';'.$nl);
		fwrite($fp, 'SET @OLD_SQL_NOTES = @@SQL_NOTES, SQL_NOTES = 0;'.$nl.$nl);
	}

	protected function writeTeardownSQL($fp) {
		$nl = $this->newline;

		// basically everything mysqldump 10.13 does too

		fwrite($fp, 'SET TIME_ZONE = @OLD_TIME_ZONE;'.$nl);
		fwrite($fp, 'SET SQL_MODE = @OLD_SQL_MODE;'.$nl);
		fwrite($fp, 'SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;'.$nl);
		fwrite($fp, 'SET UNIQUE_CHECKS = @OLD_UNIQUE_CHECKS;'.$nl);
		fwrite($fp, 'SET CHARACTER_SET_CLIENT = @OLD_CHARACTER_SET_CLIENT;'.$nl);
		fwrite($fp, 'SET CHARACTER_SET_RESULTS = @OLD_CHARACTER_SET_RESULTS;'.$nl);
		fwrite($fp, 'SET COLLATION_CONNECTION = @OLD_COLLATION_CONNECTION;'.$nl);
		fwrite($fp, 'SET SQL_NOTES = @OLD_SQL_NOTES;'.$nl);
	}

	protected function exportCreateStatement($fp, $table) {
		// CREATE-Statement
		$this->db->query("SHOW CREATE TABLE `$table`");
		foreach ($this->db->all() as $row) $create = $row['Create Table'];

		$nl = $this->newline;

		fwrite($fp, "DROP TABLE IF EXISTS `$table`;$nl");
		fwrite($fp, 'SET @saved_cs_client = @@character_set_client;'.$nl);
		fwrite($fp, 'SET character_set_client = utf8;'.$nl);
		fwrite($fp, $create.';'.$nl);
		fwrite($fp, 'SET character_set_client = @saved_cs_client;'.$nl);
	}

	protected function exportTableData($fp, $table, $diffFriendly, $prefix) {
		$columns = $this->getColumns($table);
		$sort    = $this->getSortColumns($table);
		$start   = 0;
		$nl      = $this->newline;

		do {
			// don't forget to remove the table prefix
			$this->db->select(substr($table, strlen($prefix)), '*', null, null, $sort, $start, $this->chunkSize);

			$rowNum = 0;
			$values = array();

			foreach ($this->db->all() as $row) {
				// if it's the first row of this table, disable the keys
				if ($rowNum === 0 && $start === 0) {
					fwrite($fp, $nl."ALTER TABLE `$table` DISABLE KEYS;");
				}

				$values[] = $this->getRow($row, $columns);
				++$rowNum;
			}

			if (!empty($values)) {
				$values = implode($diffFriendly ? ",$nl" : ',', $values);
				fwrite($fp, $nl."INSERT INTO `$table` VALUES".($diffFriendly ? $nl : ' ')."$values;");
				unset($values);
			}

			if ($rowNum) {
				$start += $this->chunkSize;
			}
		}
		while ($rowNum === $this->chunkSize);

		// if something has been exported, unlock the table again
		if ($start > 0) {
			fwrite($fp, $nl."ALTER TABLE `$table` ENABLE KEYS;$nl$nl");
		}
		else {
			fwrite($fp, $nl);
		}
	}

	protected function getColumns($table) {
		$columns = array();

		$this->db->query("SHOW FIELDS FROM `$table`");

		foreach ($this->db->all() as $column) {
			$name = $column['Field'];

			if (preg_match('#^(bigint|int|smallint|mediumint|tinyint|timestamp)#i', $column['Type'])) {
				$column = 'int';
			}
			elseif (preg_match('#^(float|double|decimal)#', $column['Type'])) {
				$column = 'double';
			}
			elseif (preg_match('#^(char|varchar|text|longtext|mediumtext|tinytext)#', $column['Type'])) {
				$column = 'string';
			}

			$columns[$name] = $column;
		}

		return $columns;
	}

	protected function getRow(array $row, array $columns) {
		$record = array();

		foreach ($columns as $col => $type) {
			$value = $row[$col];

			if ($value === null) {
				$record[] = 'NULL';
				continue;
			}

			switch ($type) {
				case 'int':
					$record[] = intval($value);
					break;

				case 'double':
					$record[] = sprintf('%.10F', (double) $value);
					break;

				case 'string':
				default:
					$record[] = $this->db->quote($value);
					break;
			}
		}

		return '('.implode(',', $record).')';
	}

	protected function getSortColumns($table) {
		try {
			$this->db->query('SHOW KEYS FROM `'.$table.'` WHERE Key_name = "PRIMARY"');

			$sort = array();

			foreach ($this->db->all() as $row) {
				$sort[] = '`'.$row['Column_name'].'`';
			}

			return empty($sort) ? null : implode(', ', $sort);
		}
		catch (\Exception $e) {
			// it's not a big deal if we can't do the fancy auto-sorting
			return null;
		}
	}
}
