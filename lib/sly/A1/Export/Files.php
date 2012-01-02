<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * Base class for File Exports
 */
abstract class sly_A1_Export_Files {
	abstract public function export($filename, $files, $addons);

	protected function getTempFileName() {
		$addonservice = sly_Service_Factory::getAddOnService();
		$tempdir      = new sly_Util_Directory($addonservice->internalFolder('import_export').DIRECTORY_SEPARATOR.'tmp', true);
		$tempdir->deleteFiles();

		return $tempdir.DIRECTORY_SEPARATOR.time();
	}
}
