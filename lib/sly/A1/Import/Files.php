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
 * Importer for Tar Archives
 *
 */
class sly_A1_Import_Files
{
	const TYPE_TAR = 1;
	const TYPE_ZIP = 2;

	public function __construct()
	{
		// pass...
	}

	public function import($filename)
	{
		if (empty($filename)) {
			throw new Exception(t('im_export_no_import_file_chosen'));
		}
		
		$type = $this->guessFileType($filename);

		if (!file_exists($filename)) {
			throw new Exception(t('im_export_selected_file_not_exists'));
		}

		chdir(SLY_BASE);
		if($type == self::TYPE_TAR) {

			$tar = new sly_A1_Archive_Tar($filename);

			// Extensions auslösen
			$tar = rex_register_extension_point('SLY_A1_BEFORE_FILE_IMPORT', $tar);

			// Tar auspacken
			if (!$tar->extract()) {
				chdir('sally');
				throw new Exception(t('im_export_problem_when_extracting'));
			}

			// Extensions auslösen
			$tar = rex_register_extension_point('SLY_A1_AFTER_FILE_IMPORT', $tar);
			
		}elseif($type == self::TYPE_ZIP) {
			if(class_exists('ZipArchive')) {
				$zip = new ZipArchive();
				$success = $zip->open($filename);
				if($success) {
					$zip->extractTo('./');
					$zip->close();
				}else {
					chdir('sally');
					throw new Exception(t('im_export_problem_when_extracting'));
				}
			} else {
				$zip = new PclZip($filename);
				$zip->extract();
				$success = $zip->errorCode() === PCLZIP_ERR_NO_ERROR;
				if(!$success) {
					chdir('sally');
					throw new Exception(t('im_export_problem_when_extracting'));
				}
			}
		}
		chdir('sally');
	}

	private function guessFileType($filename) {
		if(substr($filename, -7, 7) == '.tar.gz') return self::TYPE_TAR;
		if(substr($filename, -4, 4) == '.zip') return self::TYPE_ZIP;
		throw new Exception(t('im_export_no_import_file_chosen'));
	}
}
