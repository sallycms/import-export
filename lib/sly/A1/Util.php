<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
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
		$dir = SLY_DATAFOLDER.DIRECTORY_SEPARATOR.'import-export';
		$ok  = sly_Util_Directory::createHttpProtected($dir);

		if (!$ok) throw new Exception('Konnte Backup-Verzeichnis '.$dir.' nicht anlegen.');

		return $dir;
	}

	public static function getTempDir() {
		$service = sly_Service_Factory::getAddOnService();
		$dir     = $service->internalDirectory('sallycms/import-export');

		return $dir.DIRECTORY_SEPARATOR.'tmp';
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
			'addons'     => array(),
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

			$date = $archive->getExportDate();

			$result['comment']    = (string) $archive->getComment();
			$result['addons']     = sly_makeArray($archive->getAddOns());
			$result['missing']    = self::getMissingAddOns($result['addons']);
			$result['version']    = (string) $archive->getVersion();
			$result['date']       = $date ? $date : $result['date'];
			$result['compatible'] = self::isCompatible($result['version']);

			if (empty($result['comment'])) {
				$result['comment'] = $basename;
			}

			$archive->close();
		}

		return $result;
	}

	public static function compareArchiveDate(array $a1, array $a2) {
		$date1 = $a1['date'];
		$date2 = $a2['date'];

		return $date1 === $date2 ? 0 : ($date1 < $date2 ? -1 : 1);
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

			$missing = self::getMissingAddOns($archive->getAddOns());

			if (!empty($missing)) {
				throw new Exception(t('im_export_missing_addons_for_db_import').': '.implode(', ', $missing));
			}

			//throw an exception if verion does not match
			self::isCompatible($archive->getVersion(), true);

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

	private static function getMissingAddOns($addons) {
		if (!is_array($addons) || empty($addons)) return array();

		$service = sly_Service_Factory::getAddOnService();
		$missing = array();

		foreach ($addons as $addon) {
			if (is_string($addon)) {
				if (!$service->isAvailable($addon)) $missing[] = $addon;
			}
			else {
				$missing[] = implode(',', $addon);
			}
		}

		return $missing;
	}

	public static function isCompatible($dumpVersion, $throw = false) {
		if (mb_strlen($dumpVersion) === 0) return true;

		$compatible = sly_Util_Versions::isCompatible($dumpVersion);

		if (!$compatible && $throw) {
			throw new sly_Exception(t('im_export_incompatible_dump', $dumpVersion, sly_Core::getVersion('X.Y.Z')));
		}

		return $compatible;
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

	public static function backendNavigation(array $params) {
		$user = sly_Util_User::getCurrentUser();

		if ($user !== null && ($user->isAdmin() || $user->hasRight('pages', 'a1imex'))) {
			$nav   = sly_Core::getLayout()->getNavigation();
			$group = $nav->getGroup('addons');

			$nav->addPage($group, 'a1imex', t('im_export_importexport'));
		}

		return $params['subject'];
	}
}
