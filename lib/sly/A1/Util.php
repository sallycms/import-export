<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_A1_Util {
	const TYPE_TAR = 1;
	const TYPE_ZIP = 2;
	const TYPE_SQL = 3;

	public static function getIteratedFilename($filename, $ext) {
		$directory = self::getDataDir().DIRECTORY_SEPARATOR;

		if (file_exists($directory.$filename.$ext)) {
			$i = 1;
			while (file_exists($directory.$filename.'_'.$i.$ext)) $i++;
			$filename = $filename.'_'.$i;
		}

		return $filename;
	}

	private static function getArchivesBySuffix($suffix) {
		$dir    = new sly_Util_Directory(self::getDataDir());
		$folder = $dir->listPlain(true, false, false, false, 'sort');

		if (!$folder) return array();

		$filtered = array();

		foreach ($folder as $file) {
			if (sly_Util_String::endsWith($file, $suffix)) $filtered[] = $file;
		}

		return $filtered;
	}

	public static function getArchives($dir) {
		$files = self::getArchivesBySuffix('.zip');
		$files = array_merge($files, self::getArchivesBySuffix('.tar.gz'));
		$files = array_merge($files, self::getArchivesBySuffix('.sql'));
		return $files;
	}

	public static function getDataDir() {
		$dir = SLY_DATAFOLDER.DIRECTORY_SEPARATOR.'import_export';
		$ok = sly_Util_Directory::createHttpProtected($dir);
		if (!$ok) throw new Exception('Konnte Backup-Verzeichnis '.$dir.' nicht anlegen.');
		return $dir;
	}

	public static function getTempDir() {
		$service = sly_Service_Factory::getAddOnService();
		return $service->internalFolder('import_export').DIRECTORY_SEPARATOR.'tmp';
	}

	public static function cleanup() {
		$dirObj = new sly_Util_Directory(self::getTempDir(), true);
		$dirObj->deleteFiles();
	}

	public static function getArchiveInfo($filename) {
		$basename = basename($filename);
		$result   = array(
			'name'       => substr($basename, 0, strpos($basename, '.')),
			'date'       => filemtime($filename),
			'components' => array(),
			'missing'    => array(),
			'comment'    => '',
			'version'    => '',
			'compatible' => true
		);

		// Entspricht der Dateiname einem bekannten Muster?
		if (preg_match('#^(sly_\d{8})_(.*?)_(\d+)$#i', $result['name'], $matches)) {
			$result['name']        = $matches[1].'_'.$matches[3];
			$result['description'] = str_replace('_', ' ', $matches[2]);
		}
		elseif (preg_match('#^(sly_\d{8})_(.*?)$#i', $result['name'], $matches)) {
			$result['name']        = $matches[1];
			$result['description'] = str_replace('_', ' ', $matches[2]);
		}

		// check zip file comment

		if (in_array(self::guessFileType($filename), array(self::TYPE_ZIP, self::TYPE_SQL))) {
			$archive = self::getArchive($filename);

			$archive->readInfo();

			$result['comment']    = (string) $archive->getComment();
			$result['components'] = sly_makeArray($archive->getComponents());
			$result['missing']    = self::getMissingComponents($result['components']);
			$result['version']    = (string) $archive->getVersion();
			$result['date']       = $archive->getExportDate();
			$result['compatible'] = self::isCompatible($result['version']);

			if (empty($result['comment'])) {
				$result['comment'] = $basename;
			}

			$archive->close();
		}

		return $result;
	}

	public static function import($filename) {
		if (empty($filename)) {
			throw new Exception(t('im_export_no_import_file_chosen'));
		}

		$type     = self::guessFileType($filename);
		$filename = self::getDataDir().DIRECTORY_SEPARATOR.$filename;

		if (!file_exists($filename)) {
			throw new Exception(t('im_export_selected_file_not_exists'));
		}

		$cwd = getcwd();
		chdir(SLY_BASE);

		if ($type === self::TYPE_TAR) {
			$archive = new sly_A1_Archive_Tar($filename);

			// Extensions auslösen
			$archive = sly_Core::dispatcher()->filter('SLY_A1_BEFORE_FILE_IMPORT', $archive);

			// Tar auspacken
			if (!$archive->extract()) {
				chdir('sally/backend');
				throw new Exception(t('im_export_problem_when_extracting'));
			}
		}
		elseif ($type === self::TYPE_ZIP || $type === self::TYPE_SQL) {
			$archive = self::getArchive($filename);
			$archive = sly_Core::dispatcher()->filter('SLY_A1_BEFORE_FILE_IMPORT', $archive);

			$archive->readInfo();

			// check file

			$missing = self::getMissingComponents($archive->getComponents());

			if (!empty($missing)) {
				throw new Exception(t('im_export_missing_addons_for_db_import').': '.implode(', ', $missing));
			}

			if (!self::isCompatible($archive->getVersion())) {
				throw new Exception(t('im_export_incomatible_file'));
			}

			// extract

			$success = $archive->extract();
			$archive->close();

			if (!$success) {
				throw new Exception(t('im_export_problem_when_extracting'));
			}
		}

		// Extensions auslösen
		sly_Core::dispatcher()->notify('SLY_A1_AFTER_FILE_IMPORT', $archive);
		chdir($cwd);
	}

	private static function guessFileType($filename) {
		if (substr($filename, -4) == '.tar') return self::TYPE_TAR;
		if (substr($filename, -7) == '.tar.gz') return self::TYPE_TAR;
		if (substr($filename, -4) == '.zip') return self::TYPE_ZIP;
		if (substr($filename, -4) == '.sql') return self::TYPE_SQL;

		throw new Exception(t('im_export_no_import_file_chosen'));
	}

	private static function getMissingComponents($components) {
		if (!is_array($components) || empty($components)) return array();

		$addonService  = sly_Service_Factory::getAddOnService();
		$pluginService = sly_Service_Factory::getPlugInService();
		$missing       = array();

		foreach ($components as $comp) {
			if (is_string($comp)) {
				if (!$addonService->isAvailable($comp)) $missing[] = $comp;
			}
			elseif (!$pluginService->isAvailable($comp)) {
				$missing[] = implode('/', $comp);
			}
		}

		return $missing;
	}

	private static function isCompatible($version) {
		return empty($version) || sly_Service_Factory::getAddOnService()->checkVersion($version);
	}

	public static function getArchive($filename, $type = 'zip') {
		if ($type === 'sql' || substr($filename, -4) === '.sql') {
			$archive = new sly_A1_Archive_Plain($filename);
		}
		elseif (class_exists('ZipArchive', false)) {
			$archive = new sly_A1_Archive_ZipArchive($filename);
		}
		else {
			$archive = new sly_A1_Archive_PclZip($filename);
		}

		return $archive;
	}
}
