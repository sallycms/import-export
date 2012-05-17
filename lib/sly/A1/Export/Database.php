<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_A1_Export_Database {
	protected $filename;

	public function __construct() {
		$this->filename = '';
	}

	public function export($filename, $diffFriendly = false) {
		$prefix = sly_Core::getTablePrefix();

		$this->filename = $filename;

		$fp = @fopen($this->filename, 'wb');
		if (!$fp) return false;

		$sql        = sly_DB_Persistence::getInstance();
		$tables     = $sql->listTables();
		$nl         = "\n";
		$insertSize = 500;

		foreach ($tables as $idx => $table) {
			if (!sly_Util_String::startsWith($table, $prefix)) {
				unset($tables[$idx]);
			}
		}

		sly_Core::dispatcher()->notify('A1_BEFORE_DB_EXPORT');

		// Versionsstempel hinzufügen

		if (version_compare(sly_Core::getVersion('X.Y.Z'), '0.6.2', '>=')) {
			sly_DB_Dump::writeHeader($fp);
		}
		else {
			fwrite($fp, sprintf("-- Sally Database Dump Version %s\n", sly_Core::getVersion('X.Y')));
			fwrite($fp, sprintf("-- Prefix %s\n", sly_Core::getTablePrefix()));
		}

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

		foreach ($tables as $table) {
			if (!$this->includeTable($table)) {
				continue;
			}

			// CREATE-Statement
			$sql->query("SHOW CREATE TABLE `$table`");
			foreach ($sql as $row) $create = $row['Create Table'];

			fwrite($fp, "DROP TABLE IF EXISTS `$table`;\n");
			fwrite($fp, 'SET @saved_cs_client     = @@character_set_client;'.$nl);
			fwrite($fp, 'SET character_set_client = utf8;'.$nl);
			fwrite($fp, $create.';'.$nl);
			fwrite($fp, 'SET character_set_client = @saved_cs_client;'.$nl);

			// Daten-Export vorbereiten

			$fields = $this->getFields($sql, $table);
			$start  = 0;
			$max    = $insertSize;
			$sort   = null;

			try {
				$sql->query('SHOW KEYS FROM `'.$table.'` WHERE Key_name = "PRIMARY"');

				$sort = array();

				foreach ($sql->all() as $row) {
					$sort[] = '`'.$row['Column_name'].'`';
				}

				$sort = empty($sort) ? null : implode(',', $sort);
			}
			catch (Exception $e) {
				// it's not a big deal if we can't do the fancy auto-sorting
				$sort = null;
			}

			do {
				// don't forget to remove the table prefix
				$sql->select(substr($table, strlen($prefix)), '*', null, null, $sort, $start, $max);

				$rowNum = 0;
				$values = array();

				foreach ($sql as $row) {
					// if it's the first row of this table, disable the keys
					if ($rowNum === 0 && $start === 0) {
						fwrite($fp, "\nALTER TABLE `$table` DISABLE KEYS;");
					}

					$values[] = $this->getRecord($row, $fields);
					++$rowNum;
				}

				if (!empty($values)) {
					$values = implode($diffFriendly ? ",\n" : ',', $values);
					fwrite($fp, "\nINSERT INTO `$table` VALUES".($diffFriendly ? "\n" : ' ')."$values;");
					unset($values);
				}

				if ($rowNum) {
					$start += $max;
				}
			}
			while ($rowNum === $max);

			// if something has been exported, unlock the table again
			if ($start > 0) {
				fwrite($fp, "\nALTER TABLE `$table` ENABLE KEYS;\n\n");
			}
			else {
				fwrite($fp, "\n");
			}
		}

		// basically everything mysqldump 10.13 does too

		fwrite($fp, 'SET TIME_ZONE = @OLD_TIME_ZONE;'.$nl);
		fwrite($fp, 'SET SQL_MODE = @OLD_SQL_MODE;'.$nl);
		fwrite($fp, 'SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;'.$nl);
		fwrite($fp, 'SET UNIQUE_CHECKS = @OLD_UNIQUE_CHECKS;'.$nl);
		fwrite($fp, 'SET CHARACTER_SET_CLIENT = @OLD_CHARACTER_SET_CLIENT;'.$nl);
		fwrite($fp, 'SET CHARACTER_SET_RESULTS = @OLD_CHARACTER_SET_RESULTS;'.$nl);
		fwrite($fp, 'SET COLLATION_CONNECTION = @OLD_COLLATION_CONNECTION;'.$nl);
		fwrite($fp, 'SET SQL_NOTES = @OLD_SQL_NOTES;'.$nl);

		// Den Dateiinhalt geben wir nur dann weiter, wenn es unbedingt notwendig ist.

		$hasContent = true;

		if (sly_Core::dispatcher()->notify('A1_AFTER_DB_EXPORT')) {
			fclose($fp);
			$hashContent = $this->handleExtensions($filename);
		}

	  return $hasContent;
	}

	protected function includeTable($table) {
		$prefix = sly_Core::getTablePrefix();

		return
			strstr($table, $prefix) == $table && // Nur Tabellen mit dem aktuellen Präfix
			$table != $prefix.'user';            // User-Tabelle nicht exportieren
	}

	protected function getFields($sql, $table) {
		$fields = array();

		$sql->query("SHOW FIELDS FROM `$table`");

		foreach ($sql as $field) {
			$name = $field['Field'];

			if (preg_match('#^(bigint|int|smallint|mediumint|tinyint|timestamp)#i', $field['Type'])) {
				$field = 'int';
			}
			elseif (preg_match('#^(float|double|decimal)#', $field['Type'])) {
				$field = 'double';
			}
			elseif (preg_match('#^(char|varchar|text|longtext|mediumtext|tinytext)#', $field['Type'])) {
				$field = 'string';
			}

			$fields[$name] = $field;
		}

		return $fields;
	}

	protected function getRecord($row, $fields) {
		$record = array();
		$sql    = sly_DB_Persistence::getInstance();

		foreach ($fields as $col => $type) {
			$column = $row[$col];

			if ($column === null) {
				$record[] = 'NULL';
				continue;
			}

			switch ($type) {
				case 'int':
					$record[] = intval($column);
					break;

				case 'double':
					$record[] = sprintf('%.10F', (double) $column);
					break;

				case 'string':
				default:
					$record[] = $sql->quote($column);
					break;
			}
		}

		return '('.implode(',', $record).')';
	}

	protected function handleExtensions($filename) {
		$content    = file_get_contents($filename);
		$hashBefore = md5($content);
		$content    = sly_Core::dispatcher()->filter('A1_AFTER_DB_EXPORT', $content);
		$hashAfter  = md5($content);

		if ($hashAfter != $hashBefore) {
			file_put_contents($filename, $content);
		}

		return !empty($content);
	}
}
