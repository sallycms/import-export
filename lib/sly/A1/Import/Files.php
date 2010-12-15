<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
*/

/**
 * Importer for Tar Archives
 *
 */
class sly_A1_Import_Files
{
	public function __construct()
	{
		// pass...
	}

	public function import($filename)
	{
		if (empty($filename) || substr($filename, -7, 7) != '.tar.gz') {
			throw new Exception(t('im_export_no_import_file_chosen'));
		}

		if (!file_exists($filename)) {
			throw new Exception(t('im_export_selected_file_not_exists'));
		}

		$tar = new sly_A1_Archive_Tar($filename);

		chdir(SLY_BASE);

		// Extensions auslösen
		$tar = rex_register_extension_point('SLY_A1_BEFORE_FILE_IMPORT', $tar);

		// Tar auspacken
		if (!$tar->extract()) {
			chdir('sally');
			throw new Exception(t('im_export_problem_when_extracting'));
		}

		// Extensions auslösen
		$tar = rex_register_extension_point('SLY_A1_AFTER_FILE_IMPORT', $tar);
		chdir('sally');
	}
}
