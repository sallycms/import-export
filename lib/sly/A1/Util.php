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
		return $files;
	}

	public static function getDataDir() {
		$dir = SLY_DATAFOLDER.DIRECTORY_SEPARATOR.'import_export';
		$ok = sly_Util_Directory::createHttpProtected($dir);
		if (!$ok) throw new Exception('Konnte Backup-Verzeichnis '.$dir.' nicht anlegen.');
		return $dir;
	}

	public static function getArchiveInfo($filename) {
		$result = array(
			'name' => substr($filename, 0, strpos($filename, '.'))
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

		chdir(SLY_BASE);

		if ($type == self::TYPE_TAR) {
			$archive = new sly_A1_Archive_Tar($filename);

			// Extensions auslösen
			$archive = sly_Core::dispatcher()->filter('SLY_A1_BEFORE_FILE_IMPORT', $archive);

			// Tar auspacken
			if (!$archive->extract()) {
				chdir('sally/backend');
				throw new Exception(t('im_export_problem_when_extracting'));
			}
		}
		elseif($type == self::TYPE_ZIP) {
			if (class_exists('ZipArchive')) {
				$archive = new ZipArchive();

				// Extensions auslösen
				$archive = sly_Core::dispatcher()->filter('SLY_A1_BEFORE_FILE_IMPORT', $archive);
				$success = $archive->open($filename);

				if ($success) {
					$addons = $archive->getArchiveComment();
					self::checkAddOns($addons);
					$success = $archive->extractTo('./');
				}

				if ($success) {
					$archive->close();
				}
				else {
					chdir('sally/backend');
					throw new Exception(t('im_export_problem_when_extracting'));
				}
			}
			else {
				$archive = new PclZip($filename);

				// Extensions auslösen
				$archive = sly_Core::dispatcher()->filter('SLY_A1_BEFORE_FILE_IMPORT', $archive);

				$props = $archive->properties();

				if (isset($props['comment'])) {
					self::checkAddOns($props['comment']);
				}

				$archive->extract();
				$success = $archive->errorCode() === PCLZIP_ERR_NO_ERROR;

				if (!$success) {
					chdir('sally/backend');
					throw new Exception(t('im_export_problem_when_extracting'));
				}
			}
		}

		// Extensions auslösen
		$archive = sly_Core::dispatcher()->filter('SLY_A1_AFTER_FILE_IMPORT', $archive);
		chdir('sally/backend');
	}

	private static function guessFileType($filename) {
		if (substr($filename, -7) == '.tar.gz') return self::TYPE_TAR;
		if (substr($filename, -4) == '.zip') return self::TYPE_ZIP;
		throw new Exception(t('im_export_no_import_file_chosen'));
	}

	private static function checkAddOns($requiredAddOns) {
		if ($requiredAddOns === false) return true; // no comment was found inside ZIP

		$required = array_filter(explode("\n", $requiredAddOns));
		if (empty($required)) return true;

		$addonservice = sly_Service_Factory::getAddOnService();
		$available    = $addonservice->getAvailableAddons();
		$missing      = array_diff($required, array_intersect($required, $available));

		if (!empty($missing)) {
			throw new Exception(t('im_export_missing_addons_for_db_import').': '.implode(', ', $missing));
		}

		return true;
	}
}
