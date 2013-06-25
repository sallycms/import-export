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

use sly_DB_Importer;
use sly_Service_AddOn;
use sly_Util_Directory;

class Importer {
	protected $service;
	protected $addonService;
	protected $importer;

	public function __construct(Service $service, sly_Service_AddOn $addonService, sly_DB_Importer $importer) {
		$this->service      = $service;
		$this->addonService = $addonService;
		$this->importer     = $importer;
	}

	public function import($filename, $targetDir) {
		$filename = basename($filename);
		$fullPath = $this->service->getStorageDir().DIRECTORY_SEPARATOR.$filename;

		if (!is_file($fullPath)) {
			throw new Exception(t('im_export_selected_file_not_exists'));
		}

		if (!is_dir($targetDir)) {
			throw new Exception('Target directory "'.$targetDir.'" does not exist.');
		}

		@set_time_limit(0);

		// remove old files in the temp directory
		$this->service->cleanup();

		// extract the archive
		$this->extract($fullPath, $targetDir);

		// import all dumps we can find
		$dumpsFound = $this->importDumps();

		$this->dispatcher->notify('SLY_IMPORTEXPORT_AFTER_IMPORT', $fullPath, array('dumps_imported' => $dumpsFound));

		return $target;
	}

	protected function extract($filename, $targetDir) {
		$archive = Util::getArchive($filename);
		$archive = $this->dispatcher->filter('SLY_IMPORTEXPORT_BEFORE_IMPORT', $archive);

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
	}

	protected function importDumps() {
		$tmpDir = $this->service->getTempDir();
		$tmpDir = new sly_Util_Directory($tmpDir, false);
		$files  = $tmpDir->listPlain(true, false, false, true);

		foreach ($files as $file) {
			if (substr($file, -4) === '.sql') {
				$this->importer->import($file);
			}

			unlink($file);
		}

		return !empty($files);
	}
}
