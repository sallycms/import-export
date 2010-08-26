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

class sly_A1_Export_Files
{
	protected $directories;

	public function __construct()
	{

	}

	public function export($filename, $directories)
	{
		// Archiv an einem temporären Ort erzeugen (Rekursion vermeiden)

		$tmpFile = tempnam(sys_get_temp_dir(), 'sly').'.tar.gz';
		$tar     = new sly_A1_Archive_Tar($tmpFile);
		$tar     = rex_register_extension_point('SLY_A1_BEFORE_FILE_EXPORT', $tar);
		$ignores = array(
			'data/import_export'
		);

		// Backups nicht rekursiv mit sichern!

		$tar->setIgnoreList($ignores);

		// Gewählte Verzeichnisse sichern

		chdir(SLY_BASE);
		$success = $tar->create($directories);
		chdir('sally');

		// Archiv ggf. nachträglich noch verändern

		$tar = rex_register_extension_point('SLY_A1_AFTER_FILE_EXPORT', $tar, array(
			'filename' => $filename,
			'tmp_file' => $tmpFile,
			'status'   => $success
		));

		// Archiv verschieben

		if ($success) rename($tmpFile, $filename);
		return $success;
	}
}
