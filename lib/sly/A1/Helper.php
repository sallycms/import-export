<?php
/*
 * Copyright (C) 2009 REDAXO
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License Version 2 as published by the
 * Free Software Foundation.
 */

/**
 * @package redaxo4
 */
class sly_A1_Helper {
	public static function getIteratedFilename($directory, $filename, $ext) {
		$directory = rtrim($directory, '/\\').'/';

		if (file_exists($directory.$filename.$ext)) {
			$i = 1;
			while (file_exists($directory.$filename.'_'.$i.$ext)) $i++;
			$filename = $filename.'_'.$i;
		}

		return $filename;
	}

	public static function readFilteredFolder($dir, $suffix) {
		$dir = new sly_Util_Directory($dir);
		$folder = $dir->listPlain(true, false, false, false, 'sort');

		if (!$folder) return array();
		$filtered = array();

		foreach ($folder as $file) {
			if (sly_Util_String::endsWith($file, $suffix)) $filtered[] = $file;
		}

		return $filtered;
	}

	public static function getFileArchives($dir) {
		$files = array();
		$files = array_merge($files, self::readFilteredFolder($dir, '.zip'));
		$files = array_merge($files, self::readFilteredFolder($dir, '.tar.gz'));
		return $files;
	}

	public static function getFileInfo($filename) {
		$result = array(
			'real_file'   => $filename,
			'filename'    => strtolower(basename($filename)),
			'exists'      => file_exists($filename),
			'size'        => -1,
			'date'        => -1,
			'type'        => '',
			'description' => ''
		);

		if (!$result['exists']) {
			return $result;
		}

		$result['date'] = filectime($filename);
		$result['size'] = filesize($filename);

		// Erweiterung finden

		$result['type']     = substr($result['filename'], strrpos($result['filename'], '.') + 1);
		$result['filename'] = substr($result['filename'], 0, strpos($result['filename'], '.'));

		// Entspricht der Dateiname einem bekannten Muster?

		if (preg_match('#^(sly_\d{8})_(.*?)$#i', $result['filename'], $matches)) {
			$result['filename']    = $matches[1];
			$result['description'] = str_replace('_', ' ', $matches[2]);
		}
		elseif (preg_match('#^(sly_\d{8})_(.*?)_(\d+)$#i', $result['filename'], $matches)) {
			$result['filename']    = $matches[1].'_'.$matches[3];
			$result['description'] = str_replace('_', ' ', $matches[2]);
		}

		return $result;
	}

	public static function getDataDir() {
		$dir = SLY_DATAFOLDER.DIRECTORY_SEPARATOR.'import_export';
		$ok  = sly_Util_Directory::createHttpProtected($dir);
		if (!$ok) throw new Exception('Konnte Backup-Verzeichnis '.$dir.' nicht anlegen.');
		return $dir;
	}
}
