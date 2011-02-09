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

/**
 * Base class for File Exports
 */
abstract class sly_A1_Export_Files {

	public abstract function export($filename, $files);

	protected function getTempFileName() {
		$addonservice = sly_Service_Factory::getService('AddOn');
		$tempdir      = new sly_Util_Directory($addonservice->internalFolder('import_export').DIRECTORY_SEPARATOR.'tmp', true);
		$tempdir->deleteFiles();

		return $tempdir.DIRECTORY_SEPARATOR.time();
	}
}
?>
