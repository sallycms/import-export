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

use Gaufrette\Filesystem;
use sly_DB_PDO_Persistence;
use sly_Event_IDispatcher;
use sly_Service_AddOn;
use sly_Util_Directory;
use sly_Util_String;
use sly\ImportExport\Archive\Base;

class Service {
	protected $db;
	protected $dispatcher;
	protected $tempDir;
	protected $storageDir;
	protected $addonService;

	/**
	 * Constructor
	 *
	 * @param sly_DB_PDO_Persistence $db
	 * @param sly_Event_IDispatcher  $dispatcher
	 * @param string                 $tempDir
	 * @param string                 $storageDir
	 * @param sly_Service_AddOn      $service
	 */
	public function __construct(sly_DB_PDO_Persistence $db, sly_Event_IDispatcher $dispatcher, $tempDir, $storageDir, sly_Service_AddOn $service) {
		$this->db           = $db;
		$this->dispatcher   = $dispatcher;
		$this->tempDir      = $tempDir;
		$this->storageDir   = $storageDir;
		$this->addonService = $service;
	}

	public function getArchives() {
		$dir    = new sly_Util_Directory($this->getStorageDir());
		$folder = $dir->listPlain(true, false, false, false, 'sort');

		if (!$folder) {
			return array();
		}

		$filtered = array();

		foreach ($folder as $file) {
			if (sly_Util_String::endsWith($file, '.sql') || sly_Util_String::endsWith($file, '.zip')) {
				$filtered[] = $file;
			}
		}

		return $filtered;
	}

	public function getStorageDir() {
		return $this->storageDir;
	}

	public function getTempDir() {
		return $this->tempDir;
	}

	public function cleanup() {
		$dirObj = new sly_Util_Directory($this->getTempDir(), true);
		$dirObj->deleteFiles();
	}

	public function getArchiveInfo($filename) {
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

		if (in_array(Util::guessFileType($filename), array(Base::TYPE_ZIP, Base::TYPE_SQL))) {
			$archive = Util::getArchive($filename);

			$archive->readInfo();

			$date = $archive->getExportDate();

			$result['comment']    = (string) $archive->getComment();
			$result['addons']     = sly_makeArray($archive->getAddOns());
			$result['missing']    = $this->getMissingAddOns($result['addons']);
			$result['version']    = (string) $archive->getVersion();
			$result['date']       = $date ? $date : $result['date'];
			$result['compatible'] = Util::isCompatible($result['version']);

			if (empty($result['comment'])) {
				$result['comment'] = $basename;
			}

			$archive->close();
		}

		return $result;
	}

	public function extract($filename, $targetDir) {
		if (empty($filename)) {
			throw new Exception(t('im_export_no_import_file_chosen'));
		}

		if (!is_dir($targetDir)) {
			throw new Exception('Target directory "'.$targetDir.'" does not exist.');
		}

		$type     = Util::guessFileType($filename);
		$filename = $this->getStorageDir().DIRECTORY_SEPARATOR.basename($filename);

		if (!is_file($filename)) {
			throw new Exception(t('im_export_selected_file_not_exists'));
		}

		$archive = Util::getArchive($filename);
		$archive = $this->dispatcher->filter('SLY_IMPORTEXPORT_BEFORE_FILE_IMPORT', $archive);
		$archive = $this->dispatcher->filter('SLY_A1_BEFORE_FILE_IMPORT', $archive); // deprecated

		$archive->readInfo();

		// check file

		$missing = $this->getMissingAddOns($archive->getAddOns());

		if (!empty($missing)) {
			throw new Exception(t('im_export_missing_addons_for_db_import').': '.implode(', ', $missing));
		}

		// throw an exception if version does not match
		Util::isCompatible($archive->getVersion(), true);

		// extract

		$cwd = getcwd();
		chdir($targetDir);

		$success = $archive->extract();
		$archive->close();

		chdir($cwd);

		if (!$success) {
			throw new Exception(t('im_export_problem_when_extracting'));
		}

		// notify system
		$this->dispatcher->notify('SLY_IMPORTEXPORT_AFTER_FILE_IMPORT', $archive);
		$this->dispatcher->notify('SLY_A1_AFTER_FILE_IMPORT', $archive); // deprecated
	}

	protected function getMissingAddOns($addons) {
		if (!is_array($addons) || empty($addons)) {
			return array();
		}

		$missing = array();

		foreach ($addons as $addon) {
			if (is_string($addon)) {
				if (!$this->addonService->isAvailable($addon)) $missing[] = $addon;
			}
			// oldschool pre-0.7 plugin names
			else {
				$missing[] = implode(',', $addon);
			}
		}

		return $missing;
	}
}
