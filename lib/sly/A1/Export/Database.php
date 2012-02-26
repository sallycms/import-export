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

	public function export($filename) {
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

		// make sure already existing '0' values for auto_increment columns don't
		// create new incremented values when importing

		fwrite($fp, 'SET SQL_MODE=\'NO_AUTO_VALUE_ON_ZERO\';'.$nl.$nl);

		foreach ($tables as $table) {
			if (!$this->includeTable($table)) {
				continue;
			}

			// CREATE-Statement
			$sql->query("SHOW CREATE TABLE `$table`");
			foreach ($sql as $row) $create = $row['Create Table'];

			fwrite($fp, "DROP TABLE IF EXISTS `$table`;\n");
			fwrite($fp, "$create;\n");

			// Daten-Export vorbereiten

			$fields = $this->getFields($sql, $table);
			$start  = 0;
			$max    = $insertSize;

			do {
				// don't forget to remove the table prefix
				$sql->select(substr($table, strlen($prefix)), '*', null, null, null, $start, $max);

				$rowNum = 0;
				$values = array();

				foreach ($sql as $row) {
					// if it's the first row of this table, disable the keys
					if ($rowNum === 0 && $start === 0) {
						fwrite($fp, "\n/*!40000 ALTER TABLE `$table` DISABLE KEYS */;");
					}

					$values[] = $this->getRecord($row, $fields);
					++$rowNum;
				}

				if (!empty($values)) {
					$values = implode(',', $values);
					fwrite($fp, "\nINSERT INTO `$table` VALUES $values;");
					unset($values);
				}

				if ($rowNum) {
					$start += $max;
				}
			}
			while ($rowNum === $max);

			// if something has been exported, unlock the table again
			if ($start > 0) {
				fwrite($fp, "\n/*!40000 ALTER TABLE `$table` ENABLE KEYS */;\n\n");
			}
			else {
				fwrite($fp, "\n");
			}
		}

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
