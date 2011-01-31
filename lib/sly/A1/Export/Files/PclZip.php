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

class sly_A1_Export_Files_PclZip extends sly_A1_Export_Files {

	public function export($filename, $files) {
		$tmpFile = $this->getTempFileName();

		chdir(SLY_BASE);

		$archive = new PclZip($tmpFile);
		$archive->create($files);

		chdir('sally');

		$success = $archive->errorCode() === PCLZIP_ERR_NO_ERROR;

		if ($success !== true) {
			throw new sly_Exception('im_export_failed_to_create_archive');
		}
		
		return @rename($tmpFile, $filename);
	}

}
