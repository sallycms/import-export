<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\ImportExport;

use sly_Util_Directory;

class Service {
	const TYPE_ZIP = 1;
	const TYPE_SQL = 2;

	protected $container;

	public function __construct() {

	}

	public function getArchives() {
		return array_merge(
			$this->getArchivesBySuffix('.zip'),
			$this->getArchivesBySuffix('.sql')
		);
	}

	public function getStorageFilesystem() {
		return $this->filesystem;
	}

	public function getTempDir() {
		return $this->tempDir;
	}

	public function cleanup() {
		$dirObj = new sly_Util_Directory($this->getTempDir(), true);
		$dirObj->deleteFiles();
	}

	protected function getArchivesBySuffix($suffix) {
		$dir    = new sly_Util_Directory(self::getDataDir());
		$folder = $dir->listPlain(true, false, false, false, 'sort');

		if (!$folder) return array();

		$filtered = array();

		foreach ($folder as $file) {
			if (sly_Util_String::endsWith($file, $suffix)) $filtered[] = $file;
		}

		return $filtered;
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

		if ($type === self::TYPE_ZIP || $type === self::TYPE_SQL) {
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

	protected function guessFileType($filename) {
		if (substr($filename, -4) == '.zip') return self::TYPE_ZIP;
		if (substr($filename, -4) == '.sql') return self::TYPE_SQL;

		throw new Exception(t('im_export_no_import_file_chosen'));
	}

	protected function getMissingAddOns($addons) {
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
}
