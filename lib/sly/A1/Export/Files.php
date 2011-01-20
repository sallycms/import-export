<?php

/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
 */

class sly_A1_Export_Files {

	public function export($filename, $files) {
		// Archiv an einem temporären Ort erzeugen (Rekursion vermeiden)
		$tmpFile = tempnam(sys_get_temp_dir(), 'sly');

		$tmpFile = $tmpFile;
		$archive = new ZipArchive();
		$success = $archive->open($tmpFile, ZipArchive::OVERWRITE);

		if ($success === true) {
			chdir(SLY_BASE);
			foreach ($files as $file) {
				if (is_dir($file)) {
					$dir = new sly_Util_Directory($file, false);
					foreach ($dir->listRecursive(true, false) as $dirfile) {
						if (DIRECTORY_SEPARATOR === '\\') {
							$success = $archive->addFromString(str_replace(DIRECTORY_SEPARATOR, '/', $file . DIRECTORY_SEPARATOR . $dirfile), file_get_contents($file . DIRECTORY_SEPARATOR . $dirfile));
						} else {
							$success = $archive->addFile($file . DIRECTORY_SEPARATOR . $dirfile, $file . DIRECTORY_SEPARATOR . $dirfile);
						}
						if ($success !== true)
							break;
					}
				} else {
					if (DIRECTORY_SEPARATOR === '\\') {
						$success = $archive->addFromString(str_replace(DIRECTORY_SEPARATOR, '/', $file), file_get_contents($file));
					} else {
						$success = $archive->addFile($file, $file);
					}
					if ($success !== true)
						break;
				}
			}
			if ($success !== true) {
				throw new sly_Exception('im_export_failed_to_create_archive', $ok);
			}
			$archive->close();
		}

		chdir('sally');
		rename($tmpFile, $filename);
		chmod($filename, 0777);
		return $success;
	}

}
