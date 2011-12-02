<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_A1_Export_Files_PclZip extends sly_A1_Export_Files {
	public function export($filename, $files, $addons) {
		$tmpFile = $this->getTempFileName();

		chdir(SLY_BASE);

		$archive = new PclZip($tmpFile);
		$archive->create($files, PCLZIP_OPT_COMMENT, $addons);

		chdir('sally/backend');

		$success = $archive->errorCode() === PCLZIP_ERR_NO_ERROR;

		if ($success !== true) {
			throw new sly_Exception('im_export_failed_to_create_archive');
		}

		return @rename($tmpFile, $filename);
	}
}
